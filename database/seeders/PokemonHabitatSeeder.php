<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Src\Habitats\App\RecalcularNivelesMinimosHabitat;

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
        DB::table('pokemon_habitat')
            ->whereIn('pokemon_id', self::ESPECIES_NO_SALVAJES)
            ->delete();

        // La pertenencia pokémon ↔ hábitat se gestiona desde la administración.
        // El CSV solo se usa para poblar una BD vacía; en una BD ya curada no
        // se reinsertan filas antiguas. Siempre se recalculan los mínimos.
        if (DB::table('pokemon_habitat')->exists()) {
            app(RecalcularNivelesMinimosHabitat::class)->todos();

            return;
        }

        $rows = $this->loadCsv(storage_path('data/pokemon_habitat.csv'));

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
            DB::table('pokemon_habitat')->insertOrIgnore($chunk);
        }

        // Calcula el nivel mínimo de cada spawn (respeta `manual = true`).
        app(RecalcularNivelesMinimosHabitat::class)->todos();
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
