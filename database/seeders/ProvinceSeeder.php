<?php

declare(strict_types=1);

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
            //Bonguri Blanco
            'Galicia',
            //Bonguri Amarillo
            'Euskadi',
            //Bonguri Negro
            'Barcelona',
            //Bonguri Verde
            'Extremadura',
            //Bonguri Rosa
            'Nova Alacant',
            //Bonguri Azul
            'Mutxamel',
            //Bonguri Rojo
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
