<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CatalogoSeeder::class,
            //\Database\Seeders\ReclutadosSeeder::class,
        ]);

        // Usuario demo multi-jugador: identificado por name (único desde la Fase 1).
        User::updateOrCreate(
            ['name' => 'demo'],
            [
                'email' => 'demo@example.com',
                'password' => Hash::make('password'),
                'experiencia' => 0,
            ]
        );
    }
}
