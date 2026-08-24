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
            if (! isset($statsByPokemon[$pokemonId])) {
                $statsByPokemon[$pokemonId] = [];
            }
            $statsByPokemon[$pokemonId][] = $stat;
        }

        $typesByPokemon = [];
        foreach ($typesRows as $type) {
            $pokemonId = (int) $type['pokemon_id'];
            if (! isset($typesByPokemon[$pokemonId])) {
                $typesByPokemon[$pokemonId] = [];
            }
            $typesByPokemon[$pokemonId][] = $type;
        }

        $evolutionByPokemon = [];
        foreach ($evolutionRows as $evolution) {
            $pokemonId = (int) $evolution['evolved_species_id'];
            $evolutionByPokemon[$pokemonId] = $evolution;
        }

        // Seed Pokemon
        foreach ($pokemonRows as $row) {
            $speciesId = (int) $row['species_id'];
            $species = $speciesDataByKey[$speciesId] ?? null;

            if (! $species) {
                continue;
            }

            $pokemonId = (int) $row['id'];

            Pokemon::updateOrCreate(
                ['id' => $pokemonId],
                [
                    'name' => $row['name'],
                    'species_id' => $speciesId,
                    'height' => (int) $row['height'],
                    'weight' => (int) $row['weight'],
                    'base_experience' => (int) $row['base_experience'],
                    'capture_rate' => (int) $species['capture_rate'],
                    'hatch' => ! empty($species['hatch_counter']) ? (int) $species['hatch_counter'] : null,
                ]
            );

            // Seed stats for this Pokemon
            if (isset($statsByPokemon[$pokemonId])) {
                foreach ($statsByPokemon[$pokemonId] as $stat) {
                    PokemonStat::updateOrCreate(
                        ['pokemon_id' => $pokemonId, 'stat' => (int) $stat['stat_id']],
                        [
                            'base_stat' => (int) $stat['base_stat'],
                            'effort' => (int) $stat['effort'],
                        ]
                    );
                }
            }

            // Seed types for this Pokemon
            if (isset($typesByPokemon[$pokemonId])) {
                foreach ($typesByPokemon[$pokemonId] as $type) {
                    PokemonType::updateOrCreate(
                        ['pokemon_id' => $pokemonId, 'slot' => (int) $type['slot']],
                        [
                            'type' => (int) $type['type_id'],
                        ]
                    );
                }
            }

            // Seed evolution data
            $evolution = $evolutionByPokemon[$pokemonId] ?? null;
            if ($evolution !== null) {
                PokemonEvolution::updateOrCreate(
                    ['evolved_species_id' => $pokemonId],
                    [
                        'evolution_chain_id' => ! empty($species['evolution_chain_id']) ? (int) $species['evolution_chain_id'] : null,
                        'evolves_from_species_id' => ! empty($species['evolves_from_species_id']) ? (int) $species['evolves_from_species_id'] : null,
                        'minimum_level' => ! empty($evolution['minimum_level']) ? (int) $evolution['minimum_level'] : 40,
                    ]
                );
            }
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
