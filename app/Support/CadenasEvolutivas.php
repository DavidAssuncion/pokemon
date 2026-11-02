<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Pokemon;
use App\Models\PokemonEvolution;
use Illuminate\Database\Eloquent\Collection;

/**
 * Carga los miembros de las cadenas evolutivas implicadas por
 * evolution_chain_id. Sustituye a la relación de la antigua tabla
 * evolution_chains (eliminada, bug 23503): mismo criterio (columna
 * evolution_chain_id) y cubre cadenas huérfanas con fase 1 en vez de error.
 *
 * Compartido por FinalizarExploracionHandler, OtorgarRecompensasEntrenador y
 * ReclutamientoController (antes un método privado duplicado en cada uno).
 */
final class CadenasEvolutivas
{
    /**
     * Mapa de TODOS los miembros de las cadenas dadas, keyed por
     * evolution_chain_id (columna). Incluye los miembros no derrotados para
     * preservar fase y base de familia.
     *
     * @param  iterable<mixed>  $chainIds  valores evolution_chain_id (pueden venir
     *                                      mezclados/null desde distintos orígenes)
     * @return array<int, Collection<int, Pokemon>>
     */
    public static function miembrosDe(iterable $chainIds): array
    {
        $ids = collect($chainIds)
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $query = Pokemon::query();
        $query->getQuery()->whereIn('evolution_chain_id', $ids);

        return $query->get(['id', 'name', 'species_id', 'evolution_chain_id'])
            ->groupBy('evolution_chain_id')
            ->all();
    }

    /**
     * Etapa real de cada especie por cadena, derivada de las aristas de
     * `pokemon_evolution` (no del orden de `species_id`).
     *
     * Las cadenas sin aristas se omiten para que los llamadores conserven su
     * fallback legacy. Las especies sin asignar dentro de una cadena con
     * aristas se consideran etapa 1.
     *
     * @param  iterable<mixed>  $chainIds
     * @return array<int, array<int, int>> evolution_chain_id => [species_id => etapa]
     */
    public static function etapasDe(iterable $chainIds): array
    {
        $ids = collect($chainIds)
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $query = Pokemon::query();
        $query->getQuery()->whereIn('evolution_chain_id', $ids);
        $pokemons = $query->get(['id', 'species_id', 'evolution_chain_id']);

        if ($pokemons->isEmpty()) {
            return [];
        }

        /** @var array<int, array<int, true>> $especiesPorCadena */
        $especiesPorCadena = [];
        /** @var array<int, int> $especiePorPokemonId */
        $especiePorPokemonId = [];
        /** @var array<int, int> $cadenaPorEspecie */
        $cadenaPorEspecie = [];

        foreach ($pokemons as $pokemon) {
            $chainId = $pokemon->evolution_chain_id;
            $speciesId = $pokemon->species_id;
            $pokemonId = $pokemon->id;

            $especiesPorCadena[$chainId][$speciesId] = true;
            $especiePorPokemonId[$pokemonId] = $speciesId;
            $cadenaPorEspecie[$speciesId] = $chainId;
        }

        $query = PokemonEvolution::query();
        $query->getQuery()->whereIn('evolved_species_id', array_keys($especiePorPokemonId));
        $aristas = $query->get(['evolved_species_id', 'evolves_from_species_id']);

        /** @var array<int, array<int, list<int>>> $hijos */
        $hijos = [];
        /** @var array<int, array<int, true>> $tienePadre */
        $tienePadre = [];

        foreach ($aristas as $arista) {
            $childSpeciesId = $especiePorPokemonId[$arista->evolved_species_id] ?? null;
            if ($childSpeciesId === null) {
                continue;
            }

            $chainId = $cadenaPorEspecie[$childSpeciesId] ?? null;
            if ($chainId === null) {
                continue;
            }

            $parentRaw = $arista->evolves_from_species_id;
            if ($parentRaw === null) {
                continue;
            }

            $parentSpeciesId = $especiePorPokemonId[$parentRaw] ?? $parentRaw;

            if (($cadenaPorEspecie[$parentSpeciesId] ?? null) !== $chainId) {
                continue;
            }

            $hijos[$chainId][$parentSpeciesId][] = $childSpeciesId;
            $tienePadre[$chainId][$childSpeciesId] = true;
        }

        $resultado = [];

        foreach ($especiesPorCadena as $chainId => $especiesSet) {
            if (! isset($hijos[$chainId]) || $hijos[$chainId] === []) {
                continue;
            }

            $especies = array_keys($especiesSet);
            $etapas = [];
            $cola = [];

            foreach ($especies as $speciesId) {
                if (! isset($tienePadre[$chainId][$speciesId])) {
                    $etapas[$speciesId] = 1;
                    $cola[] = [$speciesId, 1];
                }
            }

            while ($cola !== []) {
                [$speciesId, $etapa] = array_shift($cola);

                foreach ($hijos[$chainId][$speciesId] ?? [] as $hijo) {
                    if (isset($etapas[$hijo])) {
                        continue;
                    }

                    $etapas[$hijo] = $etapa + 1;
                    $cola[] = [$hijo, $etapa + 1];
                }
            }

            foreach ($especies as $speciesId) {
                if (! isset($etapas[$speciesId])) {
                    $etapas[$speciesId] = 1;
                }
            }

            $resultado[$chainId] = $etapas;
        }

        return $resultado;
    }
}
