<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TipoEnum;
use App\Models\Pokedex;
use App\Models\Pokemon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createPokemon(int $id, string $name): void
    {
        Pokemon::create([
            'id' => $id,
            'name' => $name,
            'species_id' => $id,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);
    }

    public function test_pokedex_orders_pokemon_by_id(): void
    {
        $this->createPokemon(2, 'ivysaur');
        $this->createPokemon(1, 'bulbasaur');

        $response = $this->get('/pokedex');

        $response->assertOk();
        $pokemons = $response->viewData('pokemons');
        $this->assertArrayHasKey('data', $pokemons);
        $this->assertArrayHasKey('meta', $pokemons);
        $this->assertSame([1, 2], array_column($pokemons['data'], 'id'));
    }

    public function test_pokedex_passes_counts_and_types(): void
    {
        $this->createPokemon(1, 'bulbasaur');
        $this->createPokemon(2, 'ivysaur');
        $this->createPokemon(3, 'venusaur');

        Pokedex::create(['pokemon_id' => 1, 'visto' => true, 'atrapado' => true]);
        Pokedex::create(['pokemon_id' => 2, 'visto' => true, 'atrapado' => false]);

        $response = $this->get('/pokedex');

        $response->assertOk();
        $this->assertSame([
            'total' => 3,
            'vistos' => 2,
            'atrapados' => 1,
            'no_vistos' => 1,
        ], $response->viewData('counts'));
        $this->assertSame(TipoEnum::options(), $response->viewData('tipos'));
        $this->assertSame(3, $response->viewData('pokemons')['meta']['total']);
    }
}
