<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\HabitatSeeder;
use Database\Seeders\PokemonHabitatSeeder;
use Database\Seeders\PokemonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PokemonHabitatSeederTest extends TestCase
{
    use RefreshDatabase;

    private const PROVINCES = [
        1 => 'Galicia',
        2 => 'Euskadi',
        3 => 'Barcelona',
        4 => 'Extremadura',
        5 => 'Nova Alacant',
        6 => 'Mutxamel',
        7 => 'Andalucia',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Precondición de FKs: ProvinceSeeder usa updateOrCreate sin id explícito,
        // lo que depende de la secuencia PostgreSQL (no reiniciada por RefreshDatabase
        // en transacciones sucesivas). Creamos provinces con ids explícitos para que
        // HabitatSeeder (que referencia province_id 1..7 del CSV) funcione.
        foreach (self::PROVINCES as $id => $name) {
            DB::table('provinces')->insert(['id' => $id, 'name' => $name]);
        }

        // HabitatSeeder y PokemonSeeder usan upsert/updateOrCreate con id explícito
        // (no dependen de la secuencia).
        $this->seed(HabitatSeeder::class);
        $this->seed(PokemonSeeder::class);
    }

    /**
     * El seeder carga el CSV en pokemon_habitat sin errores y deja los datos en BD.
     */
    public function test_pokemon_habitat_seeder_puede_ejecutarse(): void
    {
        $this->seed(PokemonHabitatSeeder::class);

        $this->assertSame(424, DB::table('pokemon_habitat')->count());
        $this->assertDatabaseHas('pokemon_habitat', [
            'pokemon_id' => 113,
            'habitat_id' => 6,
            'level' => 2,
        ]);
    }

    /**
     * El seeder es idempotente: re-ejecutarlo no duplica filas.
     */
    public function test_pokemon_habitat_seeder_es_idempotente(): void
    {
        $this->seed(PokemonHabitatSeeder::class);
        $this->seed(PokemonHabitatSeeder::class);

        $this->assertSame(424, DB::table('pokemon_habitat')->count());
    }
}
