<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\Pokedex;
use App\Models\Pokemon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

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
        $this->actingAsUser();
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
        $user = $this->actingAsUser();
        $this->createPokemon(1, 'bulbasaur');
        $this->createPokemon(2, 'ivysaur');
        $this->createPokemon(3, 'venusaur');

        Pokedex::create(['user_id' => $user->id, 'pokemon_id' => 1, 'visto' => true, 'atrapado' => true]);
        Pokedex::create(['user_id' => $user->id, 'pokemon_id' => 2, 'visto' => true, 'atrapado' => false]);

        $response = $this->get('/pokedex');

        $response->assertOk();
        $this->assertSame([
            'total' => 3,
            'vistos' => 2,
            'atrapados' => 1,
            'no_vistos' => 1,
        ], $response->viewData('counts'));
        $this->assertSame(TipoEnum::options(), $response->viewData('tipos'));
        $this->assertSame(StatEnum::options(), $response->viewData('stats'));
        $this->assertSame(3, $response->viewData('pokemons')['meta']['total']);
    }

    public function test_pokedex_de_usuario_a_no_muestra_atrapados_de_b(): void
    {
        $usuarioA = User::factory()->create();
        $usuarioB = User::factory()->create();
        $this->createPokemon(1, 'bulbasaur');
        $this->createPokemon(2, 'ivysaur');

        Pokedex::create(['user_id' => $usuarioA->id, 'pokemon_id' => 1, 'visto' => true, 'atrapado' => true]);
        Pokedex::create(['user_id' => $usuarioB->id, 'pokemon_id' => 2, 'visto' => true, 'atrapado' => true]);

        $this->actingAs($usuarioA);

        $response = $this->get('/pokedex');

        $response->assertOk();
        $rows = $response->viewData('pokemons')['data'];
        $this->assertSame([
            ['id' => 1, 'visto' => true, 'atrapado' => true],
            ['id' => 2, 'visto' => false, 'atrapado' => false],
        ], array_map(fn (array $row): array => [
            'id' => $row['id'],
            'visto' => $row['visto'],
            'atrapado' => $row['atrapado'],
        ], $rows));
    }
}
