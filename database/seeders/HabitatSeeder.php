<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Habitat;
use Illuminate\Database\Seeder;

class HabitatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $habitats = $this->loadCsv(storage_path('data/habitats.csv'));

        foreach ($habitats as $row) {
            Habitat::updateOrCreate(
                ['id' => (int) $row['id']],
                [
                    'name' => $row['name'],
                    'province_id' => (int) $row['province_id'],
                    // D0: peligro orientativo 1–5 (bosque tranquilo = 1, cueva de
                    // dragones = 5). Determinista por id: los hábitats con nombre
                    // de cueva/zona hostil reciben peligro alto, el resto reparto.
                    'peligro' => $this->peligroOrientativo((int) $row['id'], $row['name']),
                ]
            );
        }
    }

    private function peligroOrientativo(int $id, string $nombre): int
    {
        if (str_contains(mb_strtolower($nombre), 'cueva') || str_contains(mb_strtolower($nombre), 'abismo')) {
            return 5;
        }

        if (str_contains(mb_strtolower($nombre), 'bosque')) {
            return 1;
        }

        return 1 + (($id * 7) % 5);
    }

    private function loadCsv(string $path): array
    {
        $data = [];
        if (! file_exists($path) || ($handle = fopen($path, 'r')) === false) {
            return $data;
        }

        $headers = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (empty($headers)) {
                $headers = $row;

                continue;
            }

            $data[] = array_combine($headers, $row);
        }

        fclose($handle);

        return $data;
    }
}
