<?php

declare(strict_types=1);

namespace Src\Exploraciones\App\Procesamiento;

use App\Models\ExploracionActiva;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use Src\Exploraciones\Domain\ValueObjects\PoolHabitat;
use Src\Granjas\App\ResolverBonosFamilia;
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
    ) {
    }

    /**
     * Pool de encuentros como colección tipada.
     */
    public function cargar(ExploracionActiva $exploracion): PoolHabitat
    {
        $habitat = $exploracion->habitat;
        if ($habitat === null) {
            return new PoolHabitat();
        }

        $bonos = $this->bonosFamilia ?? new ResolverBonosFamilia();
        $refugios = $bonos->refugiosDeHabitat(
            $exploracion->user_id,
            $exploracion->habitat_id,
        );

        return PoolHabitat::desdeArray(
            $habitat->pokemon()
                ->wherePivot('level', $exploracion->nivel)
                ->get()
                ->loadMissing('types', 'stats')
                ->map(fn (Pokemon $pokemon) => [
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
                    'evolution_chain_id' => $pokemon->evolution_chain_id,
                    'peso' => $bonos->factorAparicion($refugios, (int) $pokemon->evolution_chain_id),
                ])
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
