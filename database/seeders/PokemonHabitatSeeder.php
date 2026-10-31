<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PokemonHabitatSeeder extends Seeder
{
    private const CHUNK_SIZE = 500;

    /**
     * Especies que no deben aparecer como salvajes (ruta ni exploración).
     *
     * @var list<int>
     */
    private const ESPECIES_NO_SALVAJES = [132, 201, 235];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rows = $this->loadCsv(storage_path('data/pokemon_habitat.csv'));

        DB::table('pokemon_habitat')
            ->whereIn('pokemon_id', self::ESPECIES_NO_SALVAJES)
            ->delete();

        $toInsert = [];
        foreach ($rows as $row) {
            $pokemonId = (int) $row['pokemon_id'];
            if (in_array($pokemonId, self::ESPECIES_NO_SALVAJES, true)) {
                continue;
            }

            $toInsert[] = [
                'pokemon_id' => $pokemonId,
                'habitat_id' => (int) $row['habitat_id'],
                'level' => (int) $row['level'],
            ];
        }

        foreach (array_chunk($toInsert, self::CHUNK_SIZE) as $chunk) {
            DB::table('pokemon_habitat')->upsert($chunk, ['pokemon_id', 'habitat_id'], ['level']);
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
