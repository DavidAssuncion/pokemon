<?php

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
                ]
            );
        }
    }

    private function loadCsv(string $path): array
    {
        $data = [];
        if (!file_exists($path) || ($handle = fopen($path, 'r')) === false) {
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
