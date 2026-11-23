<?php

declare(strict_types=1);

namespace Src\Exploraciones\App\Procesamiento;

use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Src\Exploraciones\Domain\ValueObjects\PoolHabitat;
use Src\Granjas\App\ResolverBonosFamilia;
use Src\Shared\Domain\Ports\FactorAparicion;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Carga el pool de encuentros del hábitat de una exploración.
 *
 * Pokémon del hábitat asignados al nivel de la exploración, con sus tipos y
 * stats con effort > 0 (para caramelos EV) y el factor de aparición de granja.
 */
final class CargadorPoolHabitat
{
    public function __construct(
        private readonly ?ResolverBonosFamilia $bonosFamilia = null,
        private readonly ?FactorAparicion $factorAparicion = null,
    ) {
    }

    /**
     * Pool de encuentros como colección tipada.
     */
    public function cargar(ExploracionActiva $exploracion): PoolHabitat
    {
        $habitat = $exploracion->habitat;
        if ($habitat === null || Habitat::sinSpawns($exploracion->habitat_id)) {
            return new PoolHabitat();
        }

        $bonos = $this->bonosFamilia ?? new ResolverBonosFamilia();
        $refugios = $bonos->refugiosDeHabitat(
            $exploracion->user_id,
            $exploracion->habitat_id,
        );

        $nivelJugador = $exploracion->user?->nivel() ?? 1;

        return PoolHabitat::desdeArray(
            $habitat->pokemon()
                ->wherePivot('level', $exploracion->nivel)
                ->get()
                ->filter(function (Pokemon $pokemon) use ($nivelJugador): bool {
                    $pivote = $pokemon->getRelation('pivot');
                    if (! $pivote instanceof Pivot) {
                        return true;
                    }

                    $minimo = $pivote->getAttribute('min_level');

                    return $minimo === null || (int) $minimo <= $nivelJugador;
                })
                ->loadMissing('types', 'stats')
                ->map(function (Pokemon $pokemon) use ($bonos, $refugios, $exploracion): array {
                    $chainId = $pokemon->evolution_chain_id;

                    // Refugio y evento se aplican una sola vez: el `peso` es el
                    // peso final (override) que consume `PokemonDelPool`.
                    $factorRefugio = $bonos->factorAparicion($refugios, $chainId ?? 0);
                    $factorEvento = $this->factorAparicion?->factor($exploracion->habitat_id, $chainId) ?? 1.0;

                    return [
                        'id' => $pokemon->id,
                        'capture_rate' => $pokemon->capture_rate,
                        'hatch' => $pokemon->hatch,
                        'tipos' => $this->tiposDe($pokemon),
                        'stats' => $pokemon->stats
                            ->filter(fn (PokemonStat $stat) => $stat->effort > 0)
                            ->map(fn (PokemonStat $stat) => [
                                'stat' => $stat->stat->value,
                                'effort' => $stat->effort,
                            ])
                            ->values()
                            ->all(),
                        'evolution_chain_id' => $chainId,
                        'peso' => $factorRefugio * $factorEvento,
                    ];
                })
                ->values()
                ->all(),
        );
    }

    /**
     * @return list<TipoPokemon>
     */
    private function tiposDe(Pokemon $pokemon): array
    {
        return $pokemon->types
            ->map(fn (PokemonType $tipo): TipoPokemon => TipoPokemon::from($tipo->type->value))
            ->values()
            ->all();
    }
}
