<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\Habitat;
use App\Models\Pokedex;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use App\Models\Province;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatagridTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<TipoEnum>  $types
     * @param  array<int, int>  $stats
     */
    private function createPokemon(int $id, string $name, array $types = [], array $stats = []): void
    {
        Pokemon::create([
            'id' => $id,
            'name' => $name,
            'species_id' => $id,
            'capture_rate' => 45,
            'base_experience' => 60,
            'height' => 10,
            'weight' => 100,
        ]);

        foreach ($types as $index => $type) {
            PokemonType::create([
                'pokemon_id' => $id,
                'type' => $type,
                'slot' => $index + 1,
            ]);
        }

        foreach ($stats as $stat => $value) {
            PokemonStat::create([
                'pokemon_id' => $id,
                'stat' => $stat,
                'base_stat' => $value,
                'effort' => 0,
            ]);
        }
    }

    public function test_pokemon_list_returns_normalized_response(): void
    {
        $this->createPokemon(1, 'bulbasaur');

        $response = $this->getJson('/datagrid/pokemon');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'total',
                    'page',
                    'per_page',
                    'last_page',
                    'counts' => ['total', 'vistos', 'atrapados', 'no_vistos'],
                ],
            ]);

        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame(100, $response->json('meta.per_page'));
        $this->assertSame(1, $response->json('meta.page'));
        $this->assertSame(1, $response->json('meta.last_page'));
        $this->assertSame(1, $response->json('data.0.id'));
        $this->assertSame('bulbasaur', $response->json('data.0.name'));
    }

    public function test_pokemon_list_applies_exact_filter(): void
    {
        $this->createPokemon(1, 'bulbasaur');
        $this->createPokemon(2, 'ivysaur');

        $response = $this->getJson('/datagrid/pokemon?filter[id]=2');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame(2, $response->json('data.0.id'));
    }

    public function test_pokemon_list_applies_in_filter(): void
    {
        $this->createPokemon(1, 'bulbasaur');
        $this->createPokemon(2, 'ivysaur');
        $this->createPokemon(3, 'venusaur');

        $response = $this->getJson('/datagrid/pokemon?filter[id]=1,2');

        $response->assertOk();
        $this->assertSame([1, 2], array_column($response->json('data'), 'id'));
    }

    public function test_pokemon_list_applies_relation_filter_types(): void
    {
        $this->createPokemon(1, 'pikachu', [TipoEnum::ELECTRIC]);
        $this->createPokemon(2, 'squirtle', [TipoEnum::WATER]);

        $response = $this->getJson('/datagrid/pokemon?filter[types]=Eléctrico');

        $response->assertOk();
        $this->assertSame([1], array_column($response->json('data'), 'id'));
    }

    public function test_pokemon_list_applies_relation_filter_types_in(): void
    {
        $this->createPokemon(1, 'pikachu', [TipoEnum::ELECTRIC]);
        $this->createPokemon(2, 'squirtle', [TipoEnum::WATER]);
        $this->createPokemon(3, 'charmander', [TipoEnum::FIRE]);

        $response = $this->getJson('/datagrid/pokemon?filter[types]=Eléctrico,Agua');

        $response->assertOk();
        $this->assertSame([1, 2], array_column($response->json('data'), 'id'));
    }

    public function test_pokemon_list_applies_search_like(): void
    {
        $this->createPokemon(1, 'bulbasaur');
        $this->createPokemon(2, 'ivysaur');

        $response = $this->getJson('/datagrid/pokemon?search=bulb');

        $response->assertOk();
        $this->assertSame([1], array_column($response->json('data'), 'id'));
    }

    public function test_pokemon_list_sorts_whitelisted_column(): void
    {
        $this->createPokemon(1, 'bulbasaur');
        $this->createPokemon(2, 'ivysaur');

        $response = $this->getJson('/datagrid/pokemon?sort=name&order=desc');

        $response->assertOk();
        $this->assertSame(['ivysaur', 'bulbasaur'], array_column($response->json('data'), 'name'));
    }

    public function test_pokemon_list_ignores_non_whitelisted_filter_and_sort(): void
    {
        $this->createPokemon(1, 'bulbasaur');
        $this->createPokemon(2, 'ivysaur');

        $response = $this->getJson('/datagrid/pokemon?filter[hack]=1&sort=hack&order=desc');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_pokemon_list_clamps_per_page(): void
    {
        $this->createPokemon(1, 'bulbasaur');

        $this->assertSame(200, $this->getJson('/datagrid/pokemon?per_page=500')->json('meta.per_page'));
        $this->assertSame(1, $this->getJson('/datagrid/pokemon?per_page=0')->json('meta.per_page'));
        $this->assertSame(1, $this->getJson('/datagrid/pokemon?per_page=abc')->json('meta.per_page'));
    }

    public function test_pokemon_list_paginates(): void
    {
        $rows = [];
        for ($i = 1; $i <= 150; $i++) {
            $rows[] = [
                'id' => $i,
                'name' => "pokemon-{$i}",
                'species_id' => $i,
                'capture_rate' => 45,
                'base_experience' => 60,
                'height' => 10,
                'weight' => 100,
            ];
        }
        Pokemon::insert($rows);

        $pageOne = $this->getJson('/datagrid/pokemon?per_page=100&page=1');
        $pageTwo = $this->getJson('/datagrid/pokemon?per_page=100&page=2');

        $pageOne->assertOk();
        $pageTwo->assertOk();

        $this->assertCount(100, $pageOne->json('data'));
        $this->assertCount(50, $pageTwo->json('data'));
        $this->assertSame(150, $pageOne->json('meta.total'));
        $this->assertSame(2, $pageOne->json('meta.last_page'));
        $this->assertSame(2, $pageTwo->json('meta.page'));
    }

    public function test_unregistered_model_returns_404(): void
    {
        $this->getJson('/datagrid/secreto')->assertNotFound();
        $this->getJson('/datagrid/secreto/1/detalle')->assertNotFound();
    }

    public function test_pokemon_list_returns_global_counts(): void
    {
        $this->createPokemon(1, 'bulbasaur');
        $this->createPokemon(2, 'ivysaur');
        $this->createPokemon(3, 'venusaur');

        Pokedex::create(['pokemon_id' => 1, 'visto' => true, 'atrapado' => true]);
        Pokedex::create(['pokemon_id' => 2, 'visto' => true, 'atrapado' => false]);

        $response = $this->getJson('/datagrid/pokemon');

        $response->assertOk();
        $this->assertSame([
            'total' => 3,
            'vistos' => 2,
            'atrapados' => 1,
            'no_vistos' => 1,
        ], $response->json('meta.counts'));
    }

    public function test_pokemon_detail_returns_full_shape(): void
    {
        $this->createPokemon(1, 'bulbasaur', [TipoEnum::GRASS, TipoEnum::POISON], [
            StatEnum::HP->value => 45,
            StatEnum::ATTACK->value => 49,
            StatEnum::DEFENSE->value => 49,
            StatEnum::SPECIAL_ATTACK->value => 65,
            StatEnum::SPECIAL_DEFENSE->value => 65,
            StatEnum::SPEED->value => 45,
        ]);

        Pokedex::create(['pokemon_id' => 1, 'visto' => true, 'atrapado' => true]);

        Province::create(['id' => 1, 'name' => 'Kanto']);
        Habitat::create(['id' => 1, 'province_id' => 1, 'name' => 'Bosque']);
        DB::table('pokemon_habitat')->insert([
            'pokemon_id' => 1,
            'habitat_id' => 1,
            'level' => 1,
        ]);

        $response = $this->getJson('/datagrid/pokemon/1/detalle');

        $response->assertOk();
        $this->assertSame(1, $response->json('id'));
        $this->assertSame('bulbasaur', $response->json('name'));
        $this->assertTrue($response->json('visto'));
        $this->assertTrue($response->json('atrapado'));
        $this->assertSame(['Planta', 'Veneno'], $response->json('types'));
        $this->assertSame('Bosque', $response->json('habitat_name'));

        $stats = $response->json('stats');
        $this->assertCount(6, $stats);
        $this->assertSame('PS (HP)', $stats[0]['name']);
        $this->assertSame(45, $stats[0]['value']);
        $this->assertSame('Velocidad', $stats[5]['name']);
        $this->assertSame(45, $stats[5]['value']);
    }

    public function test_pokemon_detail_missing_returns_404(): void
    {
        $this->getJson('/datagrid/pokemon/999/detalle')->assertNotFound();
    }

    public function test_registered_models_respond_200(): void
    {
        $this->getJson('/datagrid/pokedex')->assertOk();
        $this->getJson('/datagrid/reclutado')->assertOk();
        $this->getJson('/datagrid/team')->assertOk();
        $this->getJson('/datagrid/habitat')->assertOk();
        $this->getJson('/datagrid/province')->assertOk();
    }

    public function test_pokemon_list_filter_visto_0_returns_unseen(): void
    {
        $this->createPokemon(1, 'bulbasaur');
        $this->createPokemon(2, 'ivysaur');

        Pokedex::create(['pokemon_id' => 2, 'visto' => true, 'atrapado' => false]);

        $response = $this->getJson('/datagrid/pokemon?filter[visto]=0');

        $response->assertOk();
        // Sin registro en pokedex => pokedex.visto es NULL tras el leftJoin => no visto
        $this->assertSame([1], array_column($response->json('data'), 'id'));
        $this->assertSame(1, $response->json('meta.counts.no_vistos'));
        $this->assertSame(2, $response->json('meta.counts.total'));
    }

    public function test_pokemon_list_filter_visto_1_returns_seen(): void
    {
        $this->createPokemon(1, 'bulbasaur');
        $this->createPokemon(2, 'ivysaur');

        Pokedex::create(['pokemon_id' => 2, 'visto' => true, 'atrapado' => false]);

        $response = $this->getJson('/datagrid/pokemon?filter[visto]=1');

        $response->assertOk();
        $this->assertSame([2], array_column($response->json('data'), 'id'));
    }

    public function test_pokemon_list_filter_atrapado_1_returns_captured(): void
    {
        $this->createPokemon(1, 'bulbasaur');
        $this->createPokemon(2, 'ivysaur');
        $this->createPokemon(3, 'venusaur');

        Pokedex::create(['pokemon_id' => 2, 'visto' => true, 'atrapado' => true]);
        Pokedex::create(['pokemon_id' => 3, 'visto' => true, 'atrapado' => false]);

        $response = $this->getJson('/datagrid/pokemon?filter[atrapado]=1');

        $response->assertOk();
        $this->assertSame([2], array_column($response->json('data'), 'id'));
    }

    public function test_pokemon_list_filter_atrapado_0_returns_not_captured(): void
    {
        $this->createPokemon(1, 'bulbasaur');
        $this->createPokemon(2, 'ivysaur');
        $this->createPokemon(3, 'venusaur');

        Pokedex::create(['pokemon_id' => 2, 'visto' => true, 'atrapado' => true]);
        Pokedex::create(['pokemon_id' => 3, 'visto' => true, 'atrapado' => false]);

        $response = $this->getJson('/datagrid/pokemon?filter[atrapado]=0');

        $response->assertOk();
        // Sin registro (NULL) y con atrapado=false ambos cuentan como "no atrapado"
        $this->assertSame([1, 3], array_column($response->json('data'), 'id'));
    }

    public function test_pokemon_list_items_include_icon_and_types(): void
    {
        $this->createPokemon(1, 'pikachu', [TipoEnum::ELECTRIC]);
        $this->createPokemon(2, 'squirtle', [TipoEnum::WATER]);

        $response = $this->getJson('/datagrid/pokemon');

        $response->assertOk();
        $item = $response->json('data.0');
        $this->assertSame('/images/iconos/1.webp', $item['icon']);
        $this->assertSame(['Eléctrico'], $item['types']);
        $this->assertSame('/images/iconos/2.webp', $response->json('data.1.icon'));
        $this->assertSame(['Agua'], $response->json('data.1.types'));
    }
}
