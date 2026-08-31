<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Pokemon;
use App\Models\PokemonEvolution;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use Illuminate\Database\Seeder;

class PokemonSeeder extends Seeder
{
    private const CHUNK_SIZE = 500;

    /**
     * Código de región para construir cadenas evolutivas propias de cada forma
     * regional. Los bloques 11000/12000/13000/14000 evitan que variantes de
     * alola y galar de la MISMA familia normal (meowth-alola vs meowth-galar)
     * compartan cadena.
     *
     * @var array<string, int>
     */
    private const REGION_CODES = [
        'alola' => 1,
        'galar' => 2,
        'hisui' => 3,
        'paldea' => 4,
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Load pokemon_species data indexed by id
        $speciesData = $this->loadCsv(storage_path('data/pokemon_species.csv'));
        $speciesDataByKey = [];
        foreach ($speciesData as $row) {
            $speciesDataByKey[(int) $row['id']] = $row;
        }

        // Load pokemon.csv
        $pokemonRows = $this->loadCsv(storage_path('data/pokemon.csv'));

        // Load stats, types and evolution CSVs
        $statsRows = $this->loadCsv(storage_path('data/pokemon_stats.csv'));
        $typesRows = $this->loadCsv(storage_path('data/pokemon_types.csv'));
        $evolutionRows = $this->loadCsv(storage_path('data/pokemon_evolution.csv'));

        // Index stats and types by pokemon_id for quick lookup
        $statsByPokemon = [];
        foreach ($statsRows as $stat) {
            $pokemonId = (int) $stat['pokemon_id'];
            $statsByPokemon[$pokemonId][] = $stat;
        }

        $typesByPokemon = [];
        foreach ($typesRows as $type) {
            $pokemonId = (int) $type['pokemon_id'];
            $typesByPokemon[$pokemonId][] = $type;
        }

        $evolutionByPokemon = [];
        foreach ($evolutionRows as $evolution) {
            $pokemonId = (int) $evolution['evolved_species_id'];
            $evolutionByPokemon[$pokemonId] = $evolution;
        }

        $pokemonRowsToInsert = [];
        $statsToInsert = [];
        $typesToInsert = [];

        /**
         * Filas sembradas para la segunda pasada (evoluciones): pokemonId y
         * región (null = forma normal). El species original queda en el mapa.
         *
         * @var list<array{pokemon_id: int, region: null|string, species: array<string, string>}> $seeded
         */
        $seeded = [];

        /**
         * Mapa de re-parenting evolutivo: región => species NORMAL original =>
         * pokemon.id del regional. Se construye en la pasada de clasificación y
         * se consume en la pasada de evoluciones.
         *
         * @var array<string, array<int, int>> $regionalPokemonBySpecies
         */
        $regionalPokemonBySpecies = [];

        // === Pase de clasificación: normal / regional / omitir ===
        foreach ($pokemonRows as $row) {
            $speciesId = (int) $row['species_id'];
            $species = $speciesDataByKey[$speciesId] ?? null;

            if (! $species) {
                continue;
            }

            $pokemonId = (int) $row['id'];

            $region = null;
            if ($row['is_default'] !== '1') {
                $region = $this->regionOf($row['name']);
                if ($region === null) {
                    // Forma NO regional (rotom-heat, zygarde-10, terapagos-terastal,
                    // koraidon-limited-build, castform-sunny, megas/gmax...): se omite
                    // por completo (ni pokemon, ni stats, ni types, ni evolution).
                    continue;
                }
            }

            $pokemonRowsToInsert[] = [
                'id' => $pokemonId,
                'name' => $row['name'],
                // Invariante: en los normales id == species_id (como hoy); en los
                // regionales el species es PROPIO (= pokemon.id) para que el BFS del
                // Admin y ServicioEvolucion no mezclen formas con la species normal.
                'species_id' => $region === null ? $speciesId : $pokemonId,
                'height' => (int) $row['height'],
                'weight' => (int) $row['weight'],
                'base_experience' => (int) $row['base_experience'],
                'capture_rate' => (int) $species['capture_rate'],
                'hatch' => ! empty($species['hatch_counter']) ? (int) $species['hatch_counter'] : null,
                'evolution_chain_id' => $this->chainIdToStore($region, $species),
            ];

            foreach ($statsByPokemon[$pokemonId] ?? [] as $stat) {
                $statsToInsert[] = [
                    'pokemon_id' => $pokemonId,
                    'stat' => (int) $stat['stat_id'],
                    'base_stat' => (int) $stat['base_stat'],
                    'effort' => (int) $stat['effort'],
                ];
            }

            foreach ($typesByPokemon[$pokemonId] ?? [] as $type) {
                $typesToInsert[] = [
                    'pokemon_id' => $pokemonId,
                    'slot' => (int) $type['slot'],
                    'type' => (int) $type['type_id'],
                ];
            }

            $seeded[] = [
                'pokemon_id' => $pokemonId,
                'region' => $region,
                'species' => $species,
            ];

            if ($region !== null) {
                $regionalPokemonBySpecies[$region][$speciesId] = $pokemonId;
            }
        }

        // === Segunda pasada: evoluciones (necesita el mapa regional completo) ===
        $evolutionsToInsert = [];
        foreach ($seeded as $entry) {
            $pokemonId = $entry['pokemon_id'];
            $species = $entry['species'];
            $evolvesFromSpeciesId = ! empty($species['evolves_from_species_id']) ? (int) $species['evolves_from_species_id'] : null;

            if ($evolvesFromSpeciesId === null) {
                // Sin pre-evolución → sin fila (igual que hoy). Cubre casos límite
                // aceptados: wooper-paldea no muestra evolución a clodsire (clodsire
                // no es regional) y tauros-paldea tiene 3 sabores sin evolución.
                continue;
            }

            if ($entry['region'] !== null) {
                // Regional con pre-evolución: re-parenting al regional de la MISMA
                // región cuyo species original == preNormal. Si no existe variante del
                // pre (raichu-alola ← pikachu, exeggutor-alola ← exeggcute,
                // marowak-alola ← cubone), se conserva el species normal original →
                // quedan como familias de 1 miembro en el BFS (aceptado).
                $evolvesFromSpeciesId = $regionalPokemonBySpecies[$entry['region']][$evolvesFromSpeciesId] ?? $evolvesFromSpeciesId;
            }

            $evolution = $evolutionByPokemon[$pokemonId] ?? null;
            $evolutionsToInsert[] = [
                'evolved_species_id' => $pokemonId,
                'evolves_from_species_id' => $evolvesFromSpeciesId,
                'minimum_level' => ! empty($evolution['minimum_level']) ? (int) $evolution['minimum_level'] : 40,
            ];
        }

        foreach (array_chunk($pokemonRowsToInsert, self::CHUNK_SIZE) as $chunk) {
            Pokemon::upsert($chunk, ['id'], [
                'name',
                'species_id',
                'height',
                'weight',
                'base_experience',
                'capture_rate',
                'hatch',
                'evolution_chain_id',
            ]);
        }

        foreach (array_chunk($statsToInsert, self::CHUNK_SIZE) as $chunk) {
            PokemonStat::upsert($chunk, ['pokemon_id', 'stat'], ['base_stat', 'effort']);
        }

        foreach (array_chunk($typesToInsert, self::CHUNK_SIZE) as $chunk) {
            PokemonType::upsert($chunk, ['pokemon_id', 'slot'], ['type']);
        }

        foreach (array_chunk($evolutionsToInsert, self::CHUNK_SIZE) as $chunk) {
            PokemonEvolution::upsert($chunk, ['evolved_species_id'], ['evolves_from_species_id', 'minimum_level']);
        }
    }

    /**
     * Cadena evolutiva a almacenar: la original para normales; para regionales
     * una cadena PROPIA (10000 + regionCode*1000 + chainNormal) que no colisiona
     * con las chains normales (1..~190) ni entre regiones (bloques 11000/12000/
     * 13000/14000).
     *
     * @param  array<string, string>  $species
     */
    private function chainIdToStore(?string $region, array $species): ?int
    {
        $chainNormal = ! empty($species['evolution_chain_id']) ? (int) $species['evolution_chain_id'] : null;

        if ($region === null) {
            return $chainNormal;
        }

        return 10000 + self::REGION_CODES[$region] * 1000 + ($chainNormal ?? 0);
    }

    /**
     * Región de una forma regional, o null si el nombre no es regional. Usa
     * str_contains para cubrir sufijos compuestos: darmanitan-galar-standard,
     * darmanitan-galar-zen, tauros-paldea-combat-breed...
     */
    private function regionOf(string $name): ?string
    {
        foreach (array_keys(self::REGION_CODES) as $region) {
            if (str_contains($name, '-'.$region)) {
                return $region;
            }
        }

        return null;
    }

    private function loadCsv(string $path): array
    {
        $data = [];
        $headers = [];

        if (($handle = fopen($path, 'r')) !== false) {
            $lineNum = 0;
            while (($row = fgetcsv($handle)) !== false) {
                if ($lineNum === 0) {
                    $headers = $row;
                } else {
                    $data[] = array_combine($headers, $row);
                }
                $lineNum++;
            }
            fclose($handle);
        }

        return $data;
    }
}
