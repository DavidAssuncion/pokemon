<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Pokemon;
use App\Models\PokemonEvolution;
use Src\Pokemon\Domain\Evolution\AristaEvolutiva;
use Src\Pokemon\Domain\Evolution\FamiliaEvolutiva;
use Src\Pokemon\Domain\Evolution\PokemonFamilia;
use Src\Pokemon\Domain\Evolution\ResolvedorFamiliaEvolutiva;

/**
 * Carga desde Eloquent los nodos y aristas necesarios para resolver la familia
 * evolutiva de un Pokémon y delega el cálculo en el dominio
 * (`ResolvedorFamiliaEvolutiva`).
 *
 * La carga usa `species_id` como clave de las aristas de `pokemon_evolution` y
 * amplía la familia con las especies alcanzadas en otras cadenas: así las
 * formas regionales, que tienen `evolution_chain_id` propio, aparecen junto a
 * su familia normal.
 */
final class FamiliaPokemon
{
    public static function para(Pokemon $pokemon): FamiliaEvolutiva
    {
        $actual = self::nodoDe($pokemon);
        $chainId = $pokemon->getAttribute('evolution_chain_id');

        if ($chainId === null) {
            // Sin cadena no hay evolución que consultar: singleton en memoria.
            return (new ResolvedorFamiliaEvolutiva())->resolver([$actual], []);
        }

        $miembros = self::miembrosDeCadena((int) $chainId);
        $miembros = self::incluirActual($miembros, $actual);
        $aristas = self::aristas();
        $alcanzadas = self::especiesAlcanzadas($miembros, $aristas);
        $miembros = self::completarEspeciesAlcanzadas($miembros, $alcanzadas);

        return (new ResolvedorFamiliaEvolutiva())->resolver($miembros, $aristas);
    }

    /**
     * @return list<PokemonFamilia>
     */
    private static function miembrosDeCadena(int $chainId): array
    {
        $filas = Pokemon::query()
            ->where('evolution_chain_id', $chainId)
            ->get(['id', 'name', 'species_id']);

        /** @var list<PokemonFamilia> $miembros */
        $miembros = $filas
            ->map(fn (Pokemon $pokemon): PokemonFamilia => self::nodoDe($pokemon))
            ->values()
            ->all();

        return $miembros;
    }

    /**
     * @param  list<PokemonFamilia>  $miembros
     * @return list<PokemonFamilia>
     */
    private static function incluirActual(array $miembros, PokemonFamilia $actual): array
    {
        foreach ($miembros as $miembro) {
            if ($miembro->id === $actual->id) {
                return $miembros;
            }
        }

        $miembros[] = $actual;

        return $miembros;
    }

    /**
     * Carga todas las aristas de evolución del catálogo.
     *
     * @return list<AristaEvolutiva>
     */
    private static function aristas(): array
    {
        $filas = PokemonEvolution::query()->get([
            'evolved_species_id',
            'evolves_from_species_id',
            'minimum_level',
        ]);

        /** @var list<AristaEvolutiva> $aristas */
        $aristas = [];

        foreach ($filas as $fila) {
            $desde = $fila->getAttribute('evolves_from_species_id');
            $hacia = $fila->getAttribute('evolved_species_id');

            if ($desde === null || $hacia === null || (int) $desde === (int) $hacia) {
                continue;
            }

            $nivel = $fila->getAttribute('minimum_level');

            $aristas[] = new AristaEvolutiva(
                desdeSpeciesId: (int) $desde,
                haciaSpeciesId: (int) $hacia,
                nivelMinimo: $nivel === null ? null : (int) $nivel,
            );
        }

        return $aristas;
    }

    /**
     * Cierre conectado de especies alcanzables desde las especies ya cargadas.
     *
     * El grafo se recorre sin dirección para que una forma regional incluya a
     * su pre-evolución y a los hermanos de la cadena normal.
     *
     * @param  list<PokemonFamilia>  $miembros
     * @param  list<AristaEvolutiva>  $aristas
     * @return array<int, true>
     */
    private static function especiesAlcanzadas(array $miembros, array $aristas): array
    {
        /** @var array<int, list<int>> $adyacencia */
        $adyacencia = [];

        foreach ($aristas as $arista) {
            $adyacencia[$arista->desdeSpeciesId][] = $arista->haciaSpeciesId;
            $adyacencia[$arista->haciaSpeciesId][] = $arista->desdeSpeciesId;
        }

        /** @var array<int, true> $alcanzadas */
        $alcanzadas = [];
        /** @var list<int> $pendientes */
        $pendientes = [];

        foreach ($miembros as $miembro) {
            if (isset($alcanzadas[$miembro->speciesId])) {
                continue;
            }

            $alcanzadas[$miembro->speciesId] = true;
            $pendientes[] = $miembro->speciesId;
        }

        $indice = 0;

        while ($indice < count($pendientes)) {
            $actual = $pendientes[$indice];
            $indice++;

            foreach ($adyacencia[$actual] ?? [] as $vecino) {
                if (isset($alcanzadas[$vecino])) {
                    continue;
                }

                $alcanzadas[$vecino] = true;
                $pendientes[] = $vecino;
            }
        }

        return $alcanzadas;
    }

    /**
     * Completa los nodos de las especies alcanzadas que no vinieran ya en la
     * cadena del Pokémon con una única consulta condicional.
     *
     * @param  list<PokemonFamilia>  $miembros
     * @param  array<int, true>  $alcanzadas
     * @return list<PokemonFamilia>
     */
    private static function completarEspeciesAlcanzadas(array $miembros, array $alcanzadas): array
    {
        /** @var array<int, true> $presentes */
        $presentes = [];

        foreach ($miembros as $miembro) {
            $presentes[$miembro->speciesId] = true;
        }

        $faltantes = array_values(array_filter(
            array_keys($alcanzadas),
            fn (int $speciesId): bool => ! isset($presentes[$speciesId]),
        ));

        if ($faltantes === []) {
            return $miembros;
        }

        $query = Pokemon::query();
        $query->getQuery()->whereIn('species_id', $faltantes);
        $filas = $query->get(['id', 'name', 'species_id']);

        foreach ($filas as $pokemon) {
            $miembros[] = self::nodoDe($pokemon);
        }

        return $miembros;
    }

    private static function nodoDe(Pokemon $pokemon): PokemonFamilia
    {
        return new PokemonFamilia(
            id: (int) $pokemon->getAttribute('id'),
            nombre: (string) $pokemon->getAttribute('name'),
            speciesId: (int) $pokemon->getAttribute('species_id'),
        );
    }
}
