<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Pokemon;
use App\Models\Reclutable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReclutableTest extends TestCase
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

    public function test_can_create_reclutable(): void
    {
        $pokemon = $this->createPokemon();

        $reclutable = Reclutable::create([
            'user_id' => User::factory()->create()->id,
            'pokemon_id' => $pokemon->id,
            'cantidad' => 5,
        ]);

        $this->assertDatabaseHas('reclutables', [
            'pokemon_id' => $pokemon->id,
            'cantidad' => 5,
        ]);
        $this->assertEquals($pokemon->id, $reclutable->pokemon_id);
    }

    public function test_default_cantidad_is_one(): void
    {
        $pokemon = $this->createPokemon();

        $reclutable = Reclutable::create([
            'user_id' => User::factory()->create()->id,
            'pokemon_id' => $pokemon->id,
        ]);
        $reclutable->refresh();

        $this->assertEquals(1, $reclutable->cantidad);
    }

    public function test_unique_constraint_on_pokemon_id(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        $pokemon = $this->createPokemon();
        $user = User::factory()->create();

        Reclutable::create(['user_id' => $user->id, 'pokemon_id' => $pokemon->id]);
        Reclutable::create(['user_id' => $user->id, 'pokemon_id' => $pokemon->id]);
    }

    public function test_belongs_to_pokemon_relationship(): void
    {
        $pokemon = $this->createPokemon();

        $reclutable = Reclutable::create([
            'user_id' => User::factory()->create()->id,
            'pokemon_id' => $pokemon->id,
            'cantidad' => 3,
        ]);

        $this->assertInstanceOf(Pokemon::class, $reclutable->pokemon);
        $this->assertEquals($pokemon->id, $reclutable->pokemon->id);
    }
}
