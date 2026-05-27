<?php

namespace Database\Seeders;

use App\Models\Province;
use Illuminate\Database\Seeder;

class ProvinceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $provinces = [
            'Galicia',
            'Euskadi',
            'Barcelona',
            'Extremadura',
            'Nova Alacant',
            'Mutxamel',
            'Andalucia',
        ];

        foreach ($provinces as $provinceName) {
            Province::updateOrCreate(
                ['name' => $provinceName],
                ['name' => $provinceName]
            );
        }
    }
}
