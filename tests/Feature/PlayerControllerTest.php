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
        $user = $this->actingAsUser();
        $this->createPokemon(2, 'ivysaur');
        $this->createPokemon(1, 'bulbasaur');

        // El seed ahora filtra por la pestaña por defecto (vistos): los pokémon
        // a listar deben tener su fila en pokedex con visto = true.
        Pokedex::create(['user_id' => $user->id, 'pokemon_id' => 1, 'visto' => true, 'atrapado' => false]);
        Pokedex::create(['user_id' => $user->id, 'pokemon_id' => 2, 'visto' => true, 'atrapado' => false]);

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
        // El seed se filtra a la pestaña "vistos": meta.total es el total de esa
        // pestaña (2 vistos), mientras los counts del header son globales (3).
        $this->assertSame(2, $response->viewData('pokemons')['meta']['total']);
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
        // El seed filtra por la pestaña "vistos": solo aparece el pokémon visto
        // por el usuario A (el 2 no está visto por A y no pertenece a la pestaña).
        $this->assertSame([
            ['id' => 1, 'visto' => true, 'atrapado' => true],
        ], array_map(fn (array $row): array => [
            'id' => $row['id'],
            'visto' => $row['visto'],
            'atrapado' => $row['atrapado'],
        ], $rows));
    }

    public function test_pokedex_seed_es_pagina_1_de_vistos_con_last_page_coherente(): void
    {
        $user = $this->actingAsUser();

        // 250 pokémon totales, de los cuales 130 son vistos (> per_page=120):
        // - el seed debe pedir per_page=120 sobre la pestaña "vistos" (filter[visto]=1),
        //   por lo que last_page = ceil(130 / 120) = 2, NO ceil(250 / 100).
        // - el resto (unseen) no debe entrar en la página 1 del seed.
        for ($id = 1; $id <= 250; $id++) {
            $this->createPokemon($id, 'pokemon_'.$id);
            if ($id <= 130) {
                Pokedex::create(['user_id' => $user->id, 'pokemon_id' => $id, 'visto' => true, 'atrapado' => $id % 2 === 0]);
            }
        }

        $response = $this->get('/pokedex');

        $response->assertOk();
        $page = $response->viewData('pokemons');

        $meta = $page['meta'];
        // Solo la pestaña "vistos", con per_page=120 y página 1 (seed inicial).
        $this->assertSame(130, $meta['total']);
        $this->assertSame(1, $meta['page']);
        $this->assertSame(120, $meta['per_page']);
        $this->assertSame(2, $meta['last_page']);

        // Los 120 de la primera página son todos vistos y van por id ascendente.
        $this->assertCount(120, $page['data']);
        $this->assertSame(range(1, 120), array_column($page['data'], 'id'));
        foreach ($page['data'] as $row) {
            $this->assertTrue($row['visto']);
        }
    }
}
