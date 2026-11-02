<?php

declare(strict_types=1);

namespace Src\Huevos\App;

use App\Models\Huevo;
use App\Models\Reclutado;
use Src\Granjas\App\ResolverBonosFamilia;
use Src\Huevos\Domain\CalculadorEstadoHuevo;
use Src\Huevos\Domain\DataTransferObjects\DatosTiempoHuevo;
use Src\Huevos\Domain\EstadoHuevo;
use Src\Huevos\Domain\ReglasHuevo;
use Src\Pokemon\Domain\Stats\GeneradorIvs;
use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;
use Src\Shared\Domain\ProbabilidadesShiny;

/**
 * Crea un huevo validando:
 *  - ≥2 reclutados DISTINTOS de la misma familia (evolution_chain_id; especies
 *    distintas de la misma cadena valen);
 *  - máx. 1 huevo en gestación simultáneo (puede convivir con uno incubando);
 *  - máx. 1 huevo activo (sin eclosionar) por familia.
 * Lanzado desde POST /api/huevos (user-initiated; sin auto-trigger).
 */
final class CrearHuevo
{
    public function __construct(
        private readonly ResolverBonosFamilia $bonosFamilia,
    ) {
    }
    public function crear(
        int $userId,
        int $evolutionChainId,
        ?callable $aleatorioShiny = null,
        ?callable $aleatorioIvs = null,
    ): Huevo {
        $reclutados = Reclutado::with('pokemon')
            ->where('user_id', $userId)
            ->whereHas('pokemon', fn ($q) => $q->where('evolution_chain_id', $evolutionChainId))
            ->get()
            ->reject(fn (Reclutado $reclutado): bool => $reclutado->pokemon === null);

        if ($reclutados->count() < ReglasHuevo::RECLUTADOS_MINIMOS) {
            throw new ViolacionReglaNegocio(
                'Necesitas '.ReglasHuevo::RECLUTADOS_MINIMOS.' reclutados de la misma familia para crear un huevo'
            );
        }

        $huevosActivos = Huevo::where('user_id', $userId)
            ->whereNull('eclosionado_en')
            ->get();

        $hayGestacionActiva = $huevosActivos->contains(
            fn (Huevo $huevo): bool => CalculadorEstadoHuevo::estadoDe(
                $huevo->created_at,
                new DatosTiempoHuevo(
                    captureRate: $huevo->capture_rate ?? 0,
                    hatch: $huevo->hatch ?? 1,
                    nidoNivel: $huevo->nido_nivel ?? 0,
                ),
                now(),
            )->estado === EstadoHuevo::GESTANDO,
        );

        if ($hayGestacionActiva) {
            throw new ViolacionReglaNegocio('Ya tienes un huevo en gestación; espera a que termine para poner otro');
        }

        $hayActivoEnLaFamilia = $huevosActivos->contains(
            fn (Huevo $huevo): bool => (int) $huevo->evolution_chain_id === $evolutionChainId,
        );

        if ($hayActivoEnLaFamilia) {
            throw new ViolacionReglaNegocio('Ya tienes un huevo activo de esta familia');
        }

        /** @var Reclutado $representante */
        $representante = $reclutados
            ->sortBy(fn (Reclutado $reclutado): int => $reclutado->pokemon->species_id)
            ->first();

        $pokemon = $representante->pokemon;

        if ($pokemon === null || $pokemon->hatch === null || $pokemon->capture_rate === null) {
            throw new ViolacionReglaNegocio('Esta especie no tiene datos de eclosión');
        }

        $shiny = ProbabilidadesShiny::esShiny(ProbabilidadesShiny::HUEVO, $aleatorioShiny);
        $nidoNivel = $this->bonosFamilia->nidoNivel($userId, $evolutionChainId);
        $ivs = $shiny ? GeneradorIvs::maximos() : GeneradorIvs::huevo($nidoNivel, $aleatorioIvs);

        $huevo = new Huevo();
        $huevo->user_id = $userId;
        $huevo->pokemon_id = $pokemon->id;
        $huevo->evolution_chain_id = (int) $pokemon->evolution_chain_id;
        $huevo->capture_rate = $pokemon->capture_rate;
        $huevo->hatch = $pokemon->hatch;
        $huevo->nido_nivel = $nidoNivel;
        $huevo->es_shiny = $shiny;
        $huevo->ivs = $ivs;
        $huevo->save();

        return $huevo;
    }
}
