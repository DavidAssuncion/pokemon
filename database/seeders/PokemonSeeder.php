<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Pokemon;
use App\Models\PokemonEvolution;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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
     * Rangos de pokemon.id que corresponden a formas regionales. Se usa el ID
     * porque pokemon.csv perdió el sufijo de región en 38 formas de Alola y
     * Galar, así que el nombre ya no permite detectarlas.
     *
     * @var array<string, list<array{0: int, 1: int}>>
     */
    private const REGION_RANGES = [
        'alola' => [[10091, 10092], [10100, 10115]],
        'galar' => [[10161, 10180]],
        'hisui' => [[10229, 10244]],
        'paldea' => [[10250, 10253]],
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

        /**
         * Filas de pokemon_evolution agrupadas por especie evolucionada: una
         * misma evolución puede tener varias filas (formas regionales) y hay
         * que conservarlas todas para elegir bien el padre y el nivel.
         *
         * @var array<int, list<array<string, string>>> $evolutionRowsByEvolved
         */
        $evolutionRowsByEvolved = [];
        foreach ($evolutionRows as $evolution) {
            $evolvedId = (int) $evolution['evolved_species_id'];
            $evolutionRowsByEvolved[$evolvedId][] = $evolution;
        }

        $statsToInsert = [];
        $typesToInsert = [];

        /**
         * Filas sembradas para la segunda pasada (evoluciones), indexadas por
         * pokemonId: región (null = forma normal), species original y fila de
         * pokemon.csv.
         *
         * @var array<int, array{region: null|string, species: array<string, string>, row: array<string, string>}> $seeded
         */
        $seeded = [];

        /**
         * Cadena evolutiva definitiva por pokemonId. Se calcula en la pasada de
         * clasificación y se reasigna en la de evoluciones cuando el pokémon se
         * cuelga de su pre-evolución regional.
         *
         * @var array<int, null|int> $chainById
         */
        $chainById = [];

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

            $region = $row['is_default'] === '1' ? null : $this->regionById($pokemonId);
            if ($row['is_default'] !== '1' && $region === null) {
                // Forma NO regional (rotom-heat, zygarde-10, terapagos-terastal,
                // koraidon-limited-build, castform-sunny, megas/gmax...): se omite
                // por completo (ni pokemon, ni stats, ni types, ni evolution).
                continue;
            }

            $chainById[$pokemonId] = $this->chainIdToStore($region, $species);

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

            // Invariante: en los normales id == species_id; en los regionales el
            // species es PROPIO (= pokemon.id) para que el BFS del Admin y
            // ServicioEvolucion no mezclen formas con la species normal.
            $seeded[$pokemonId] = [
                'region' => $region,
                'species' => $species,
                'row' => $row,
            ];

            if ($region !== null) {
                $regionalPokemonBySpecies[$region][$speciesId] = $pokemonId;
            }
        }

        // === Segunda pasada: evoluciones (necesita el mapa regional completo) ===
        $evolutionsToInsert = [];
        foreach ($seeded as $pokemonId => $entry) {
            $speciesParent = ! empty($entry['species']['evolves_from_species_id'])
                ? (int) $entry['species']['evolves_from_species_id']
                : null;

            if ($speciesParent === null) {
                // Sin pre-evolución → sin fila. Cubre casos límite aceptados:
                // wooper-paldea no muestra evolución a clodsire y tauros-paldea
                // tiene 3 sabores sin evolución.
                continue;
            }

            $rows = $evolutionRowsByEvolved[$pokemonId] ?? [];
            $level = $this->minimumLevelOf($rows) ?? 40;

            $hasOwnRow = false;
            foreach ($rows as $row) {
                if (($row['base_form_id'] ?? '') === '') {
                    $hasOwnRow = true;

                    break;
                }
            }

            $parent = $speciesParent;

            if ($entry['region'] === null && ! $hasOwnRow && $rows !== []) {
                // Evolución cuya única fuente es una forma regional (perrserker,
                // obstagoon, cursola, sirfetchd, mr-rime, runerigus, sneasler,
                // overqwil, clodsire): se cuelga de la pre-evolución regional y
                // comparte su cadena. Si esa forma no se siembra (basculegion 902
                // ← basculin-white-striped 10247), se conserva el padre normal.
                $baseFormId = (int) $rows[0]['base_form_id'];
                if (isset($seeded[$baseFormId])) {
                    $parent = $baseFormId;
                    $chainById[$pokemonId] = $chainById[$baseFormId];
                }
            }

            if ($entry['region'] !== null) {
                // Regional con pre-evolución: re-parenting al regional de la MISMA
                // región cuyo species original == preNormal. Si no existe variante del
                // pre (raichu-alola ← pikachu, exeggutor-alola ← exeggcute,
                // marowak-alola ← cubone), se conserva el species normal original →
                // quedan como familias de 1 miembro en el BFS (aceptado).
                $parent = $regionalPokemonBySpecies[$entry['region']][$parent] ?? $parent;
            }

            $evolutionsToInsert[] = [
                'evolved_species_id' => $pokemonId,
                'evolves_from_species_id' => $parent,
                'minimum_level' => $level,
            ];
        }

        // === Filas de pokemon (después del plan: la cadena puede cambiar) ===
        $pokemonRowsToInsert = [];
        foreach ($seeded as $pokemonId => $entry) {
            $row = $entry['row'];
            $pokemonRowsToInsert[] = [
                'id' => $pokemonId,
                'name' => Str::ucfirst($row['name']),
                'species_id' => $entry['region'] === null ? (int) $row['species_id'] : $pokemonId,
                'height' => (int) $row['height'],
                'weight' => (int) $row['weight'],
                'base_experience' => (int) $row['base_experience'],
                'capture_rate' => (int) $entry['species']['capture_rate'],
                'hatch' => ! empty($entry['species']['hatch_counter']) ? (int) $entry['species']['hatch_counter'] : null,
                'evolution_chain_id' => $chainById[$pokemonId],
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
     * Región de una forma regional por pokemon.id, o null si el ID no cae en
     * ningún bloque regional. No se usa el nombre porque pokemon.csv perdió el
     * sufijo en 38 formas de Alola y Galar.
     */
    private function regionById(int $pokemonId): ?string
    {
        foreach (self::REGION_RANGES as $region => $ranges) {
            foreach ($ranges as [$from, $to]) {
                if ($pokemonId >= $from && $pokemonId <= $to) {
                    return $region;
                }
            }
        }

        return null;
    }

    /**
     * Nivel mínimo de evolución de las filas del CSV, o null si ninguna lo
     * informa.
     *
     * @param  list<array<string, string>>  $rows
     */
    private function minimumLevelOf(array $rows): ?int
    {
        $levels = [];
        foreach ($rows as $row) {
            if (! empty($row['minimum_level'])) {
                $levels[] = (int) $row['minimum_level'];
            }
        }

        return $levels === [] ? null : min($levels);
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
