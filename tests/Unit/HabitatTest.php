<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\Province;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HabitatTest extends TestCase
{
    use RefreshDatabase;

    private function createHabitat(array $overrides = []): Habitat
    {
        Province::create(['id' => 1, 'name' => 'Kanto']);

        return Habitat::create(array_merge([
            'id' => 1,
            'name' => 'Bosque',
            'province_id' => 1,
        ], $overrides));
    }

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

    public function test_has_pokemons_json_field(): void
    {
        $habitat = $this->createHabitat();

        $this->assertDatabaseHas('habitats', [
            'id' => $habitat->id,
        ]);

        $habitat->refresh();
        // pokemons column exists and is cast to array
        $this->assertNull($habitat->pokemons);
    }

    public function test_can_store_and_retrieve_pokemons_json(): void
    {
        $habitat = $this->createHabitat();

        $pokemonsData = [
            ['id' => 1, 'name' => 'bulbasaur', 'level' => 1],
            ['id' => 2, 'name' => 'ivysaur', 'level' => 2],
        ];

        $habitat->update(['pokemons' => $pokemonsData]);
        $habitat->refresh();

        $this->assertIsArray($habitat->pokemons);
        $this->assertCount(2, $habitat->pokemons);
        $this->assertEquals('bulbasaur', $habitat->pokemons[0]['name']);
    }

    public function test_pokemons_json_default_is_null(): void
    {
        $habitat = $this->createHabitat();
        $habitat->refresh();

        $this->assertNull($habitat->pokemons);
    }

    public function test_has_exploraciones_has_many_relationship(): void
    {
        $habitat = $this->createHabitat();
        $team = Team::create(['name' => 'Alpha', 'user_id' => User::factory()->create()->id]);

        ExploracionActiva::create([
            'user_id' => $team->user_id,
            'equipo_id' => $team->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
        ]);

        $this->assertCount(1, $habitat->exploraciones);
        $this->assertInstanceOf(ExploracionActiva::class, $habitat->exploraciones->first());
    }

    public function test_belongs_to_province_relationship(): void
    {
        $habitat = $this->createHabitat();

        $this->assertInstanceOf(Province::class, $habitat->province);
        $this->assertEquals('Kanto', $habitat->province->name);
    }

    public function test_belongs_to_many_pokemon_relationship(): void
    {
        $habitat = $this->createHabitat();
        $pokemon = $this->createPokemon();

        DB::table('pokemon_habitat')->insert([
            'pokemon_id' => $pokemon->id,
            'habitat_id' => $habitat->id,
            'level' => 1,
        ]);

        $habitat->refresh();
        $this->assertCount(1, $habitat->pokemon);
        $this->assertEquals($pokemon->id, $habitat->pokemon->first()->id);
    }
}
