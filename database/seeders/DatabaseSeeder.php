<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\HabitatSeeder;
use Database\Seeders\ProvinceSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ProvinceSeeder::class,
            HabitatSeeder::class,
            PokemonSeeder::class,
            \Database\Seeders\ReclutadosSeeder::class,
        ]);

        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
