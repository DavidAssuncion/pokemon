<?php

declare(strict_types=1);

namespace Src\Exploraciones\App;

use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use Illuminate\Support\Collection;
use Src\Exploraciones\Domain\Recompensas\PokemonDerrotado;

/**
 * Normaliza pokémon Eloquent (con stats y types cargados) a la forma pura que
 * consume CalculadorRecompensas, calculando la fase evolutiva con el mapa de
 * miembros por familia (columna evolution_chain_id; la tabla evolution_chains
 * ya no existe). El handler expande una entrada por derrota (misma especie
 * puede aparecer N veces).
 */
final class NormalizadorPokemonDerrotado
{
    /**
     * @param  Collection<int, Pokemon>  $pokemons  Colección (eloquent o base) con stats y types cargados.
     * @param  array<int, Collection<int, Pokemon>>|null  $miembrosPorCadena  Mapa de TODOS los
     *                                              miembros de cada familia, keyed por
     *                                              evolution_chain_id (columna). Sin mapa (o sin
     *                                              la cadena), la fase no se puede calcular → 1.
     * @return Collection<int, PokemonDerrotado>
     */
    public static function normalizar(Collection $pokemons, ?array $miembrosPorCadena = null): Collection
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
                fase: self::fase($pokemon, $miembrosPorCadena),
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
     * Fase evolutiva: nº de miembros de la familia (por columna
     * evolution_chain_id) con species_id <= al actual. Sin familia en el mapa
     * → fase 1 (equivalente al caso antiguo de relación null).
     *
     * @param  array<int, Collection<int, Pokemon>>|null  $miembrosPorCadena
     */
    private static function fase(Pokemon $pokemon, ?array $miembrosPorCadena): int
    {
        if ($pokemon->evolution_chain_id === null || $miembrosPorCadena === null) {
            return 1;
        }

        $miembros = $miembrosPorCadena[$pokemon->evolution_chain_id] ?? null;

        return $miembros?->where('species_id', '<=', $pokemon->species_id)->count() ?? 1;
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
