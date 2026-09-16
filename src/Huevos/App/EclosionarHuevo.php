<?php

declare(strict_types=1);

namespace Src\Huevos\App;

use App\Models\Huevo;
use App\Models\Reclutado;
use App\Support\CrearReclutado;
use Src\Huevos\Domain\CalculadorEstadoHuevo;
use Src\Huevos\Domain\EstadoHuevo;
use Src\Shared\Domain\Exceptions\RecursoNoExiste;
use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;

/**
 * Eclosiona un huevo listo: marca eclosionado_en y crea el reclutado con los IVs
 * del huevo y su estado shiny. Idempotente (un huevo ya eclosionado se rechaza)
 * y con defensa en profundidad sobre la pertenencia al usuario.
 */
final class EclosionarHuevo
{
    public function __construct(
        private readonly CrearReclutado $crearReclutado,
    ) {
    }

    public function eclosionar(Huevo $huevo, int $userId): Reclutado
    {
        $huevo->load('pokemon');

        // Defensa en profundidad: el route-model binding ya acota con el scope
        // BelongsToUser; si se llega aquí con un huevo ajeno (p.ej. desde un
        // job con el modelo cargado sin el scope), se responde 404.
        if ((int) $huevo->user_id !== $userId) {
            throw new RecursoNoExiste();
        }

        // Idempotencia: eclosionar dos veces el mismo huevo crearía reclutados duplicados.
        if ($huevo->eclosionado_en !== null) {
            throw new ViolacionReglaNegocio('Este huevo ya eclosionó.');
        }

        $estado = CalculadorEstadoHuevo::estadoDe(
            $huevo->created_at,
            $huevo->capture_rate,
            $huevo->hatch ?? 1,
            now(),
        );

        if ($estado->estado !== EstadoHuevo::LISTO) {
            throw new ViolacionReglaNegocio('El huevo aún no está listo para eclosionar');
        }

        $huevo->update(['eclosionado_en' => now()]);

        return $this->crearReclutado->crear(
            userId: $userId,
            pokemonId: $huevo->pokemon_id,
            ivs: $huevo->ivs,
            esShiny: $huevo->es_shiny,
        );
    }
}
