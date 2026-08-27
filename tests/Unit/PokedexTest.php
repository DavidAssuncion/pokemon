<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Pokedex;
use App\Models\Pokemon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PokedexTest extends TestCase
{
    use RefreshDatabase;

    private function createPokemon(array $overrides = []): Pokemon
    {
        return Pokemon::create(array_merge([
            'id' => 1,
            'name' => 'bulbasaur',
            'species_id' => 1,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ], $overrides));
    }

    public function test_can_create_pokedex_record(): void
    {
        $pokemon = $this->createPokemon();

        $pokedex = Pokedex::create([
            'pokemon_id' => $pokemon->id,
            'visto' => true,
            'atrapado' => true,
        ]);

        $this->assertDatabaseHas('pokedex', [
            'pokemon_id' => $pokemon->id,
            'visto' => true,
            'atrapado' => true,
        ]);
        $this->assertEquals($pokemon->id, $pokedex->pokemon_id);
    }

    public function test_default_values_are_false(): void
    {
        $pokemon = $this->createPokemon();

        $pokedex = Pokedex::create([
            'pokemon_id' => $pokemon->id,
        ]);
        $pokedex->refresh();

        $this->assertFalse($pokedex->visto);
        $this->assertFalse($pokedex->atrapado);
    }

    public function test_unique_constraint_on_pokemon_id(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        $pokemon = $this->createPokemon();

        Pokedex::create(['pokemon_id' => $pokemon->id]);
        Pokedex::create(['pokemon_id' => $pokemon->id]);
    }

    public function test_belongs_to_pokemon_relationship(): void
    {
        $pokemon = $this->createPokemon();

        $pokedex = Pokedex::create([
            'pokemon_id' => $pokemon->id,
            'visto' => true,
            'atrapado' => false,
        ]);

        $this->assertInstanceOf(Pokemon::class, $pokedex->pokemon);
        $this->assertEquals($pokemon->id, $pokedex->pokemon->id);
    }
}
