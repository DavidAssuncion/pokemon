<?php

namespace Database\Seeders;

use App\Models\Habitat;
use App\Models\Province;
use Illuminate\Database\Seeder;

class HabitatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $provinceIds = Province::pluck('id', 'name');

        $habitats = [
            ['name' => 'Bahía Crustáceo', 'province' => 'Mutxamel'],
            ['name' => 'Bosque Brumoso', 'province' => 'Galicia'],
            ['name' => 'Bosque Crecido', 'province' => 'Galicia'],
            ['name' => 'Bosque Elemental', 'province' => 'Galicia'],
            ['name' => 'Bosque Nido', 'province' => 'Galicia'],
            ['name' => 'Bosque Sanador', 'province' => 'Galicia'],
            ['name' => 'Bosque Trans', 'province' => 'Galicia'],
            ['name' => 'Campo Terroso', 'province' => 'Galicia'],
            ['name' => 'Caverna Gélida', 'province' => 'Galicia'],
            ['name' => 'Charca Tortuga', 'province' => 'Galicia'],
            ['name' => 'Charca Renacuajo', 'province' => 'Galicia'],
            ['name' => 'Ciénaga Maná', 'province' => 'Galicia'],
            ['name' => 'Corriente Marina', 'province' => 'Galicia'],
            ['name' => 'Cráter', 'province' => 'Galicia'],
            ['name' => 'Mar Sereno', 'province' => 'Galicia'],
            ['name' => 'Lago Místico', 'province' => 'Galicia'],
            ['name' => 'Fosa Abisal', 'province' => 'Galicia'],
            ['name' => 'Mina Magnética', 'province' => 'Galicia'],
            ['name' => 'Pradera Celeste', 'province' => 'Galicia'],
            ['name' => 'Pradera Salvaje', 'province' => 'Galicia'],
            ['name' => 'Río Manso', 'province' => 'Galicia'],
            ['name' => 'Selva', 'province' => 'Galicia'],
            ['name' => 'Vieja Central', 'province' => 'Galicia'],
            ['name' => 'Tierra Quemada', 'province' => 'Galicia'],
            ['name' => 'Central Energía', 'province' => 'Galicia'],
            ['name' => 'Bosque Secreto', 'province' => 'Galicia'],
            ['name' => 'Sabana', 'province' => 'Galicia'],
            ['name' => 'Prado Trueno', 'province' => 'Galicia'],
            ['name' => 'Lago Cascada', 'province' => 'Galicia'],
        ];

        foreach ($habitats as $habitat) {
            Habitat::updateOrCreate(
                ['name' => $habitat['name']],
                ['province_id' => $provinceIds[$habitat['province']] ?? $provinceIds['Galicia']]
            );
        }
    }
}
