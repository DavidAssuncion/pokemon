<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CatalogoSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the catalog data (idempotent: safe to re-run on every boot).
     */
    public function run(): void
    {
        $this->call([
            ProvinceSeeder::class,
            HabitatSeeder::class,
            PokemonSeeder::class,
        ]);
    }
}
