<?php

declare(strict_types=1);

namespace Src\Huevos\App;

use App\Models\Huevo;
use App\Models\Reclutado;
use App\Support\CrearReclutado;
use Src\Granjas\App\ResolverBonosFamilia;
use Src\Huevos\Domain\CalculadorEstadoHuevo;
use Src\Huevos\Domain\DataTransferObjects\DatosTiempoHuevo;
use Src\Huevos\Domain\EstadoHuevo;
use Src\Reclutamiento\Domain\DataTransferObjects\DatosNuevoReclutado;
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
        private readonly ResolverBonosFamilia $bonosFamilia,
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
            new DatosTiempoHuevo(
                captureRate: $huevo->capture_rate ?? 0,
                hatch: $huevo->hatch ?? 1,
                nidoNivel: $huevo->nido_nivel ?? 0,
            ),
            now(),
        );

        if ($estado->estado !== EstadoHuevo::LISTO) {
            throw new ViolacionReglaNegocio('El huevo aún no está listo para eclosionar');
        }

        $huevo->eclosionado_en = now();
        $huevo->save();

        $evolutionChainId = $huevo->pokemon->evolution_chain_id ?? 0;
        $evs = $evolutionChainId > 0
            ? $this->bonosFamilia->evsIniciales($userId, $evolutionChainId)
            : null;

        return $this->crearReclutado->crear(
            new DatosNuevoReclutado(
                userId: $userId,
                pokemonId: $huevo->pokemon_id,
                ivs: $huevo->ivs,
                esShiny: $huevo->es_shiny,
            ),
            $evs,
        );
    }
}
