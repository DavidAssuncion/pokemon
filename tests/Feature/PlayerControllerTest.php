<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Pokemon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_pokedex_orders_pokemon_by_id(): void
    {
        Pokemon::create([
            'id' => 2,
            'name' => 'ivysaur',
            'species_id' => 2,
            'capture_rate' => 45,
            'base_experience' => 142,
            'height' => 10,
            'weight' => 130,
        ]);
        Pokemon::create([
            'id' => 1,
            'name' => 'bulbasaur',
            'species_id' => 1,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);

        $response = $this->get('/pokedex');

        $response->assertOk();
        $pokemons = $response->viewData('pokemons');
        $this->assertSame([1, 2], array_column($pokemons, 'id'));
    }
}
