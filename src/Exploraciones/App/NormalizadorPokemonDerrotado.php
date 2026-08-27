<?php

declare(strict_types=1);

namespace Src\Exploraciones\App;

use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use Illuminate\Support\Collection;
use Src\Exploraciones\Domain\Recompensas\PokemonDerrotado;

/**
 * Normaliza pokémon Eloquent (con evolutionChain.pokemon, stats y types cargados)
 * a la forma pura que consume CalculadorRecompensas, calculando la fase evolutiva.
 * El handler expande una entrada por derrota (misma especie puede aparecer N veces).
 */
final class NormalizadorPokemonDerrotado
{
    /**
     * @param  Collection<int, Pokemon>  $pokemons  Colección (eloquent o base)
     *                                              con evolutionChain.pokemon, stats y types cargados.
     * @return Collection<int, PokemonDerrotado>
     */
    public static function normalizar(Collection $pokemons): Collection
    {
        return $pokemons->map(
            fn (Pokemon $pokemon): PokemonDerrotado => new PokemonDerrotado(
                id: $pokemon->id,
                baseExperience: $pokemon->base_experience,
                evolutionChainId: $pokemon->evolution_chain_id,
                speciesId: $pokemon->species_id,
                captureRate: $pokemon->capture_rate,
                tipos: self::tipos($pokemon),
                stats: self::esfuerzos($pokemon),
                fase: self::fase($pokemon),
            )
        );
    }

    /**
     * Stats normalizados: stat como int (independiente del enum) y effort.
     *
     * @return Collection<int, array{stat: int, effort: int}>
     */
    private static function esfuerzos(Pokemon $pokemon): Collection
    {
        return $pokemon->stats
            ->map(function (PokemonStat $stat): array {
                /** @var int $statId */
                $statId = $stat->stat->value;

                return [
                    'stat' => $statId,
                    'effort' => $stat->effort,
                ];
            })
            ->values();
    }

    /**
     * Fase evolutiva: nº de pokémon de la cadena con species_id <= al actual.
     */
    private static function fase(Pokemon $pokemon): int
    {
        $cadena = $pokemon->evolutionChain;

        return $cadena !== null
            ? $cadena->pokemon->where('species_id', '<=', $pokemon->species_id)->count()
            : 1;
    }

    /**
     * @return list<string>
     */
    private static function tipos(Pokemon $pokemon): array
    {
        return $pokemon->types
            ->map(fn (PokemonType $tipo): string => $tipo->tipo_nombre)
            ->filter()
            ->values()
            ->all();
    }
}
