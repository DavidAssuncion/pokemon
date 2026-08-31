<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PokemonHabitatSeeder extends Seeder
{
    private const CHUNK_SIZE = 500;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rows = $this->loadCsv(storage_path('data/pokemon_habitat.csv'));

        $toInsert = [];
        foreach ($rows as $row) {
            $toInsert[] = [
                'pokemon_id' => (int) $row['pokemon_id'],
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
