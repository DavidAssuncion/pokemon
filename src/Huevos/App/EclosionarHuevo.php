<?php

declare(strict_types=1);

namespace Src\Huevos\App;

use App\Models\Huevo;
use App\Models\Reclutado;
use App\Support\CrearReclutado;
use Src\Huevos\Domain\CalculadorEstadoHuevo;
use Src\Huevos\Domain\EstadoHuevo;
use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;

/**
 * Eclosiona un huevo listo: marca eclosionado_en y crea el reclutado con los IVs
 * del huevo y su estado shiny.
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

        $estado = CalculadorEstadoHuevo::estadoDe(
            $huevo->created_at,
            (int) $huevo->capture_rate,
            (int) ($huevo->hatch ?? 1),
            now(),
        );

        if ($estado['estado'] !== EstadoHuevo::LISTO) {
            throw new ViolacionReglaNegocio('El huevo aún no está listo para eclosionar');
        }

        $huevo->update(['eclosionado_en' => now()]);

        return $this->crearReclutado->crear(
            userId: $userId,
            pokemonId: (int) $huevo->pokemon_id,
            ivs: $huevo->ivs,
            esShiny: (bool) $huevo->es_shiny,
        );
    }
}
