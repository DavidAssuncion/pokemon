<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\Habitat;
use App\Models\Pokedex;
use App\Models\Pokemon;
use App\Models\PokemonEvolution;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatagridTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    /**
     * @param  list<TipoEnum>  $types
     * @param  array<int, int>  $stats
     * @param  array<int, int>  $efforts  stat => effort (EVs otorgados)
     * @param  int|null  $speciesId  Por defecto, el id del Pokémon
     * @param  int|null  $evolutionChainId  Cadena evolutiva del Pokémon
     */
    private function createPokemon(
        int $id,
        string $name,
        array $types = [],
        array $stats = [],
        array $efforts = [],
        ?int $speciesId = null,
        ?int $evolutionChainId = null,
    ): void {
        Pokemon::create([
            'id' => $id,
            'name' => $name,
            'species_id' => $speciesId ?? $id,
            'capture_rate' => 45,
            'base_experience' => 60,
            'height' => 10,
            'weight' => 100,
            'evolution_chain_id' => $evolutionChainId,
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
                'effort' => $efforts[$stat] ?? 0,
            ]);
        }
    }

    public function test_pokemon_list_returns_normalized_response(): void
    {
        $this->actingAsUser();
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
        $this->assertFalse($response->json('data.0.visto'));
        $this->assertFalse($response->json('data.0.atrapado'));
    }

    public function test_pokemon_list_applies_exact_filter(): void
    {
        $this->actingAsUser();
        $this->createPokemon(1, 'bulbasaur');
        $this->createPokemon(2, 'ivysaur');

        $response = $this->getJson('/datagrid/pokemon?filter[id]=2');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame(2, $response->json('data.0.id'));
    }

    public function test_pokemon_list_applies_in_filter(): void
    {
        $this->actingAsUser();
        $this->createPokemon(1, 'bulbasaur');
        $this->createPokemon(2, 'ivysaur');
        $this->createPokemon(3, 'venusaur');

        $response = $this->getJson('/datagrid/pokemon?filter[id]=1,2');

        $response->assertOk();
        $this->assertSame([1, 2], array_column($response->json('data'), 'id'));
    }

    public function test_pokemon_list_applies_relation_filter_types(): void
    {
        $this->actingAsUser();
        $this->createPokemon(1, 'pikachu', [TipoEnum::ELECTRIC]);
        $this->createPokemon(2, 'squirtle', [TipoEnum::WATER]);

        $response = $this->getJson('/datagrid/pokemon?filter[types]=Eléctrico');

        $response->assertOk();
        $this->assertSame([1], array_column($response->json('data'), 'id'));
    }

    public function test_pokemon_list_applies_relation_filter_types_in(): void
    {
        $this->actingAsUser();
        $this->createPokemon(1, 'pikachu', [TipoEnum::ELECTRIC]);
        $this->createPokemon(2, 'squirtle', [TipoEnum::WATER]);
        $this->createPokemon(3, 'charmander', [TipoEnum::FIRE]);

        $response = $this->getJson('/datagrid/pokemon?filter[types]=Eléctrico,Agua');

        $response->assertOk();
        $this->assertSame([1, 2], array_column($response->json('data'), 'id'));
    }

    public function test_pokemon_list_applies_search_like(): void
    {
        $this->actingAsUser();
        $this->createPokemon(1, 'bulbasaur');
        $this->createPokemon(2, 'ivysaur');

        $response = $this->getJson('/datagrid/pokemon?search=bulb');

        $response->assertOk();
        $this->assertSame([1], array_column($response->json('data'), 'id'));
    }

    public function test_pokemon_list_sorts_whitelisted_column(): void
    {
        $this->actingAsUser();
        $this->createPokemon(1, 'bulbasaur');
        $this->createPokemon(2, 'ivysaur');

        $response = $this->getJson('/datagrid/pokemon?sort=name&order=desc');

        $response->assertOk();
        $this->assertSame(['ivysaur', 'bulbasaur'], array_column($response->json('data'), 'name'));
    }

    public function test_pokemon_list_ignores_non_whitelisted_filter_and_sort(): void
    {
        $this->actingAsUser();
        $this->createPokemon(1, 'bulbasaur');
        $this->createPokemon(2, 'ivysaur');

        $response = $this->getJson('/datagrid/pokemon?filter[hack]=1&sort=hack&order=desc');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_pokemon_list_clamps_per_page(): void
    {
        $this->actingAsUser();
        $this->createPokemon(1, 'bulbasaur');

        $this->assertSame(200, $this->getJson('/datagrid/pokemon?per_page=500')->json('meta.per_page'));
        $this->assertSame(1, $this->getJson('/datagrid/pokemon?per_page=0')->json('meta.per_page'));
        $this->assertSame(1, $this->getJson('/datagrid/pokemon?per_page=abc')->json('meta.per_page'));
    }

    public function test_pokemon_list_paginates(): void
    {
        $this->actingAsUser();

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
        $this->actingAsUser();

        $this->getJson('/datagrid/secreto')->assertNotFound();
        $this->getJson('/datagrid/secreto/1/detalle')->assertNotFound();
    }

    public function test_pokemon_list_returns_global_counts(): void
    {
        $user = $this->actingAsUser();
        $this->createPokemon(1, 'bulbasaur');
        $this->createPokemon(2, 'ivysaur');
        $this->createPokemon(3, 'venusaur');

        Pokedex::create(['user_id' => $user->id, 'pokemon_id' => 1, 'visto' => true, 'atrapado' => true]);
        Pokedex::create(['user_id' => $user->id, 'pokemon_id' => 2, 'visto' => true, 'atrapado' => false]);

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
        $user = $this->actingAsUser();
        $this->createPokemon(1, 'bulbasaur', [TipoEnum::GRASS, TipoEnum::POISON], [
            StatEnum::HP->value => 45,
            StatEnum::ATTACK->value => 49,
            StatEnum::DEFENSE->value => 49,
            StatEnum::SPECIAL_ATTACK->value => 65,
            StatEnum::SPECIAL_DEFENSE->value => 65,
            StatEnum::SPEED->value => 45,
        ]);

        Pokedex::create(['user_id' => $user->id, 'pokemon_id' => 1, 'visto' => true, 'atrapado' => true]);

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

        $this->assertSame([
            ['id' => 12, 'label' => 'Planta', 'slug' => 'planta', 'icon' => '/images/type/planta.webp'],
            ['id' => 4, 'label' => 'Veneno', 'slug' => 'veneno', 'icon' => '/images/type/veneno.webp'],
        ], $response->json('type_details'));

        $location = $response->json('location');
        $this->assertSame(1, $location['habitat_id']);
        $this->assertSame('Bosque', $location['habitat_name']);
        $this->assertSame('/habitats-img/1.webp?v=1', $location['habitat_image']);
        $this->assertSame(1, $location['province_id']);
        $this->assertSame('Kanto', $location['province_name']);
        $this->assertSame('/habitats/1', $location['url']);

        $this->assertNull($response->json('family.evolution_chain_id'));
        $this->assertCount(1, $response->json('family.members'));
        $this->assertTrue($response->json('family.members.0.is_current'));

        $stats = $response->json('stats');
        $this->assertCount(6, $stats);
        $this->assertSame('PS (HP)', $stats[0]['name']);
        $this->assertSame(45, $stats[0]['value']);
        $this->assertSame('Velocidad', $stats[5]['name']);
        $this->assertSame(45, $stats[5]['value']);
    }

    public function test_pokemon_detail_missing_returns_404(): void
    {
        $this->actingAsUser();

        $this->getJson('/datagrid/pokemon/999/detalle')->assertNotFound();
    }

    public function test_pokemon_detail_unseen_returns_404(): void
    {
        $this->actingAsUser();
        $this->createPokemon(1, 'bulbasaur');

        $this->getJson('/datagrid/pokemon/1/detalle')->assertNotFound();
    }

    public function test_pokemon_detail_unseen_with_unlock_returns_detail(): void
    {
        $this->actingAsUser();
        $this->createPokemon(1, 'bulbasaur', [TipoEnum::GRASS], [StatEnum::HP->value => 45]);

        $response = $this->getJson('/datagrid/pokemon/1/detalle?unlock=1');

        $response->assertOk();
        $this->assertFalse($response->json('visto'));
        $this->assertFalse($response->json('atrapado'));
        $this->assertSame(['Planta'], $response->json('types'));
        $this->assertSame('Planta', $response->json('type_details.0.label'));
        $this->assertSame('planta', $response->json('type_details.0.slug'));
        $this->assertSame('/images/type/planta.webp', $response->json('type_details.0.icon'));
        $this->assertSame([['name' => 'PS (HP)', 'value' => 45]], $response->json('stats'));
        $this->assertNull($response->json('habitat_name'));
        $this->assertNull($response->json('location'));
        $this->assertNull($response->json('family.evolution_chain_id'));
        $this->assertCount(1, $response->json('family.members'));
    }

    public function test_pokemon_detail_type_details_are_ordered_by_slot(): void
    {
        $user = $this->actingAsUser();
        $this->createPokemon(1, 'bulbasaur');

        PokemonType::create(['pokemon_id' => 1, 'type' => TipoEnum::POISON, 'slot' => 2]);
        PokemonType::create(['pokemon_id' => 1, 'type' => TipoEnum::GRASS, 'slot' => 1]);

        Pokedex::create(['user_id' => $user->id, 'pokemon_id' => 1, 'visto' => true, 'atrapado' => false]);

        $response = $this->getJson('/datagrid/pokemon/1/detalle');

        $response->assertOk();
        $this->assertSame(['Planta', 'Veneno'], array_column($response->json('type_details'), 'label'));
        $this->assertSame(['planta', 'veneno'], array_column($response->json('type_details'), 'slug'));
    }

    public function test_pokemon_detail_family_orders_branches_and_levels(): void
    {
        $user = $this->actingAsUser();

        $this->createPokemon(1, 'base', evolutionChainId: 400);
        $this->createPokemon(2, 'rama-baja', evolutionChainId: 400);
        $this->createPokemon(3, 'rama-alta', evolutionChainId: 400);

        PokemonEvolution::create([
            'evolved_species_id' => 2,
            'evolves_from_species_id' => 1,
            'minimum_level' => 16,
        ]);
        PokemonEvolution::create([
            'evolved_species_id' => 3,
            'evolves_from_species_id' => 1,
            'minimum_level' => 32,
        ]);

        Pokedex::create(['user_id' => $user->id, 'pokemon_id' => 1, 'visto' => true, 'atrapado' => false]);

        $response = $this->getJson('/datagrid/pokemon/1/detalle');

        $response->assertOk();
        $this->assertSame(400, $response->json('family.evolution_chain_id'));

        $members = $response->json('family.members');
        $this->assertCount(3, $members);
        $this->assertSame([1, 2, 3], array_column($members, 'id'));
        $this->assertSame([1, 2, 2], array_column($members, 'stage'));
        $this->assertSame([null, 1, 1], array_column($members, 'parent_id'));
        $this->assertSame([null, 16, 32], array_column($members, 'minimum_level'));
        $this->assertSame([0, 0, 1], array_column($members, 'branch_index'));
        $this->assertSame('/images/iconos_webp/1.webp', $members[0]['icon']);
        $this->assertTrue($members[0]['is_current']);
        $this->assertFalse($members[1]['is_current']);
        $this->assertSame(count($members), count(array_unique(array_column($members, 'id'))));
    }

    public function test_pokemon_detail_family_singleton_without_chain(): void
    {
        $this->actingAsUser();
        $this->createPokemon(7, 'eevee');

        $response = $this->getJson('/datagrid/pokemon/7/detalle?unlock=1');

        $response->assertOk();
        $this->assertNull($response->json('family.evolution_chain_id'));

        $member = $response->json('family.members.0');
        $this->assertCount(1, $response->json('family.members'));
        $this->assertSame(7, $member['id']);
        $this->assertSame(7, $member['species_id']);
        $this->assertSame(1, $member['stage']);
        $this->assertNull($member['parent_id']);
        $this->assertNull($member['minimum_level']);
        $this->assertSame(0, $member['branch_index']);
        $this->assertTrue($member['is_current']);
        $this->assertNull($response->json('location'));
    }

    public function test_pokemon_detail_family_includes_regional_form_across_chains(): void
    {
        $user = $this->actingAsUser();

        $this->createPokemon(172, 'pichu', evolutionChainId: 10);
        $this->createPokemon(25, 'pikachu', evolutionChainId: 10);
        $this->createPokemon(26, 'raichu', evolutionChainId: 10);
        $this->createPokemon(10100, 'raichu-alola', speciesId: 10100, evolutionChainId: 11010);

        PokemonEvolution::create([
            'evolved_species_id' => 25,
            'evolves_from_species_id' => 172,
            'minimum_level' => 5,
        ]);
        PokemonEvolution::create([
            'evolved_species_id' => 26,
            'evolves_from_species_id' => 25,
            'minimum_level' => 20,
        ]);
        PokemonEvolution::create([
            'evolved_species_id' => 10100,
            'evolves_from_species_id' => 25,
            'minimum_level' => 20,
        ]);

        Pokedex::create(['user_id' => $user->id, 'pokemon_id' => 10100, 'visto' => true, 'atrapado' => false]);

        $response = $this->getJson('/datagrid/pokemon/10100/detalle');

        $response->assertOk();
        $this->assertSame(11010, $response->json('family.evolution_chain_id'));

        $members = $response->json('family.members');
        $this->assertCount(4, $members);
        $this->assertSame([172, 25, 26, 10100], array_column($members, 'id'));
        $this->assertSame([1, 2, 3, 3], array_column($members, 'stage'));

        $regional = $members[3];
        $this->assertSame(25, $regional['parent_id']);
        $this->assertSame(20, $regional['minimum_level']);
        $this->assertTrue($regional['is_current']);
        $this->assertFalse($members[0]['is_current']);
    }

    public function test_registered_models_respond_200(): void
    {
        $this->actingAsUser();

        $this->getJson('/datagrid/pokedex')->assertOk();
        $this->getJson('/datagrid/reclutado')->assertOk();
        $this->getJson('/datagrid/team')->assertOk();
        $this->getJson('/datagrid/habitat')->assertOk();
        $this->getJson('/datagrid/province')->assertOk();
    }

    public function test_pokemon_list_filter_visto_0_returns_unseen(): void
    {
        $user = $this->actingAsUser();
        $this->createPokemon(1, 'bulbasaur');
        $this->createPokemon(2, 'ivysaur');

        Pokedex::create(['user_id' => $user->id, 'pokemon_id' => 2, 'visto' => true, 'atrapado' => false]);

        $response = $this->getJson('/datagrid/pokemon?filter[visto]=0');

        $response->assertOk();
        // Sin registro en pokedex => pokedex.visto es NULL tras el leftJoin => no visto
        $this->assertSame([1], array_column($response->json('data'), 'id'));
        $this->assertFalse($response->json('data.0.visto'));
        $this->assertSame(1, $response->json('meta.counts.no_vistos'));
        $this->assertSame(2, $response->json('meta.counts.total'));
    }

    public function test_pokemon_list_filter_visto_1_returns_seen(): void
    {
        $user = $this->actingAsUser();
        $this->createPokemon(1, 'bulbasaur');
        $this->createPokemon(2, 'ivysaur');

        Pokedex::create(['user_id' => $user->id, 'pokemon_id' => 2, 'visto' => true, 'atrapado' => false]);

        $response = $this->getJson('/datagrid/pokemon?filter[visto]=1');

        $response->assertOk();
        $this->assertSame([2], array_column($response->json('data'), 'id'));
        $this->assertTrue($response->json('data.0.visto'));
    }

    public function test_pokemon_list_filter_atrapado_1_returns_captured(): void
    {
        $user = $this->actingAsUser();
        $this->createPokemon(1, 'bulbasaur');
        $this->createPokemon(2, 'ivysaur');
        $this->createPokemon(3, 'venusaur');

        Pokedex::create(['user_id' => $user->id, 'pokemon_id' => 2, 'visto' => true, 'atrapado' => true]);
        Pokedex::create(['user_id' => $user->id, 'pokemon_id' => 3, 'visto' => true, 'atrapado' => false]);

        $response = $this->getJson('/datagrid/pokemon?filter[atrapado]=1');

        $response->assertOk();
        $this->assertSame([2], array_column($response->json('data'), 'id'));
        $this->assertTrue($response->json('data.0.atrapado'));
    }

    public function test_pokemon_list_filter_atrapado_0_returns_not_captured(): void
    {
        $user = $this->actingAsUser();
        $this->createPokemon(1, 'bulbasaur');
        $this->createPokemon(2, 'ivysaur');
        $this->createPokemon(3, 'venusaur');

        Pokedex::create(['user_id' => $user->id, 'pokemon_id' => 2, 'visto' => true, 'atrapado' => true]);
        Pokedex::create(['user_id' => $user->id, 'pokemon_id' => 3, 'visto' => true, 'atrapado' => false]);

        $response = $this->getJson('/datagrid/pokemon?filter[atrapado]=0');

        $response->assertOk();
        // Sin registro (NULL) y con atrapado=false ambos cuentan como "no atrapado"
        $this->assertSame([1, 3], array_column($response->json('data'), 'id'));
        $this->assertFalse($response->json('data.0.atrapado'));
    }

    public function test_pokemon_list_items_include_icon_and_types(): void
    {
        $this->actingAsUser();
        $this->createPokemon(1, 'pikachu', [TipoEnum::ELECTRIC]);
        $this->createPokemon(2, 'squirtle', [TipoEnum::WATER]);

        $response = $this->getJson('/datagrid/pokemon');

        $response->assertOk();
        $item = $response->json('data.0');
        $this->assertSame('/images/iconos_webp/1.webp', $item['icon']);
        $this->assertSame(['Eléctrico'], $item['types']);
        $this->assertSame('/images/iconos_webp/2.webp', $response->json('data.1.icon'));
        $this->assertSame(['Agua'], $response->json('data.1.types'));
    }

    public function test_pokemon_list_filter_effort_by_label(): void
    {
        $this->actingAsUser();

        // bulbasaur: Ataque effort 2, HP effort 0
        $this->createPokemon(1, 'bulbasaur', [], [
            StatEnum::HP->value => 45,
            StatEnum::ATTACK->value => 49,
        ], [StatEnum::ATTACK->value => 2]);
        // ivysaur: Ataque effort 0, HP effort 1
        $this->createPokemon(2, 'ivysaur', [], [
            StatEnum::HP->value => 60,
            StatEnum::ATTACK->value => 62,
        ], [StatEnum::HP->value => 1]);

        $response = $this->getJson('/datagrid/pokemon?filter[effort]=Ataque');

        $response->assertOk();
        $this->assertSame([1], array_column($response->json('data'), 'id'));
    }

    public function test_pokemon_list_filter_effort_by_id(): void
    {
        $this->actingAsUser();

        $this->createPokemon(1, 'bulbasaur', [], [
            StatEnum::HP->value => 45,
            StatEnum::ATTACK->value => 49,
        ], [StatEnum::ATTACK->value => 2]);
        $this->createPokemon(2, 'ivysaur', [], [
            StatEnum::HP->value => 60,
            StatEnum::ATTACK->value => 62,
        ], [StatEnum::HP->value => 1]);

        $response = $this->getJson('/datagrid/pokemon?filter[effort]=2');

        $response->assertOk();
        $this->assertSame([1], array_column($response->json('data'), 'id'));
    }

    public function test_pokemon_list_filter_effort_label_invalid_ignored(): void
    {
        $this->actingAsUser();
        $this->createPokemon(1, 'bulbasaur', [], [StatEnum::ATTACK->value => 49], [StatEnum::ATTACK->value => 2]);
        $this->createPokemon(2, 'ivysaur');

        $response = $this->getJson('/datagrid/pokemon?filter[effort]=NoExiste');

        $response->assertOk();
        // Label inválido => statId devuelve null => filtro ignorado silenciosamente
        $this->assertCount(2, $response->json('data'));
    }

    public function test_pokemon_list_filter_effort_zero_ignored(): void
    {
        $this->actingAsUser();
        $this->createPokemon(1, 'bulbasaur', [], [StatEnum::ATTACK->value => 49], [StatEnum::ATTACK->value => 2]);
        $this->createPokemon(2, 'ivysaur');

        $response = $this->getJson('/datagrid/pokemon?filter[effort]=0');

        $response->assertOk();
        // effort=0 no es un stat válido => filtro ignorado (no "0 resultados")
        $this->assertCount(2, $response->json('data'));
    }

    public function test_pokemon_list_filter_effort_combines_with_types(): void
    {
        $this->actingAsUser();

        // pikachu: Eléctrico + Ataque effort 2
        $this->createPokemon(1, 'pikachu', [TipoEnum::ELECTRIC], [StatEnum::ATTACK->value => 55], [StatEnum::ATTACK->value => 2]);
        // raichu: Eléctrico + Ataque effort 0
        $this->createPokemon(2, 'raichu', [TipoEnum::ELECTRIC], [StatEnum::ATTACK->value => 90]);
        // squirtle: Agua + Ataque effort 1
        $this->createPokemon(3, 'squirtle', [TipoEnum::WATER], [StatEnum::ATTACK->value => 48], [StatEnum::ATTACK->value => 1]);

        $response = $this->getJson('/datagrid/pokemon?filter[effort]=Ataque&filter[types]=Eléctrico');

        $response->assertOk();
        $this->assertSame([1], array_column($response->json('data'), 'id'));
    }

    public function test_pokedex_de_un_usuario_no_muestra_atrapados_de_otro(): void
    {
        $usuarioA = User::factory()->create();
        $usuarioB = User::factory()->create();
        $this->createPokemon(1, 'bulbasaur');
        $this->createPokemon(2, 'ivysaur');

        // Ambos usuarios tienen filas de bulbasaur (atrapado por los dos) y B atrapa ivysaur.
        Pokedex::create(['user_id' => $usuarioA->id, 'pokemon_id' => 1, 'visto' => true, 'atrapado' => true]);
        Pokedex::create(['user_id' => $usuarioB->id, 'pokemon_id' => 1, 'visto' => true, 'atrapado' => true]);
        Pokedex::create(['user_id' => $usuarioB->id, 'pokemon_id' => 2, 'visto' => true, 'atrapado' => true]);

        $this->actingAs($usuarioA);

        $response = $this->getJson('/datagrid/pokemon');

        $response->assertOk();
        // Sin filas duplicadas por el leftJoin multi-usuario: un item por pokémon.
        $this->assertCount(2, $response->json('data'));
        $this->assertTrue($response->json('data.0.atrapado'), 'bulbasaur atrapado por A');
        $this->assertFalse($response->json('data.1.atrapado'), 'ivysaur no es de A');
        $this->assertSame([
            'total' => 2,
            'vistos' => 1,
            'atrapados' => 1,
            'no_vistos' => 1,
        ], $response->json('meta.counts'));
    }
}
