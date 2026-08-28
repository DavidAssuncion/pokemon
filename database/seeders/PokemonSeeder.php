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
        $evolutionsToInsert = [];

        foreach ($pokemonRows as $row) {
            $speciesId = (int) $row['species_id'];
            $species = $speciesDataByKey[$speciesId] ?? null;

            if (! $species) {
                continue;
            }

            $pokemonId = (int) $row['id'];

            $pokemonRowsToInsert[] = [
                'id' => $pokemonId,
                'name' => $row['name'],
                'species_id' => $speciesId,
                'height' => (int) $row['height'],
                'weight' => (int) $row['weight'],
                'base_experience' => (int) $row['base_experience'],
                'capture_rate' => (int) $species['capture_rate'],
                'hatch' => ! empty($species['hatch_counter']) ? (int) $species['hatch_counter'] : null,
                'evolution_chain_id' => ! empty($species['evolution_chain_id']) ? (int) $species['evolution_chain_id'] : null,
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

            $evolution = $evolutionByPokemon[$pokemonId] ?? null;
            $evolvesFromSpeciesId = ! empty($species['evolves_from_species_id']) ? (int) $species['evolves_from_species_id'] : null;

            if ($evolvesFromSpeciesId !== null) {
                $evolutionsToInsert[] = [
                    'evolved_species_id' => $pokemonId,
                    'evolves_from_species_id' => $evolvesFromSpeciesId,
                    'minimum_level' => ! empty($evolution['minimum_level']) ? (int) $evolution['minimum_level'] : 40,
                ];
            }
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
