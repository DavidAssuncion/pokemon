<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Caramelo;
use App\Models\EvolutionChain;
use App\Models\Pokemon;
use App\Models\Reclutable;
use App\Models\Reclutado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReclutamientoControllerTest extends TestCase
{
    use RefreshDatabase;

    private function crearPokemon(
        int $id = 1,
        string $name = 'bulbasaur',
        int $speciesId = 1,
        ?int $evolutionChainId = null,
    ): Pokemon {
        return Pokemon::create([
            'id' => $id,
            'name' => $name,
            'species_id' => $speciesId,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
            'evolution_chain_id' => $evolutionChainId,
        ]);
    }

    public function test_reclutamiento_page_lists_reclutables(): void
    {
        $pokemon = $this->crearPokemon();
        Reclutable::create(['pokemon_id' => $pokemon->id, 'cantidad' => 5]);

        $response = $this->get('/reclutamiento');

        $response->assertOk();
        $reclutables = $response->viewData('reclutables');
        $this->assertCount(1, $reclutables);
        $this->assertSame('bulbasaur', $reclutables[0]['nombre']);
        $this->assertSame(5, $reclutables[0]['cantidad']);
        $this->assertSame($pokemon->id, $reclutables[0]['pokemon_id']);
    }

    public function test_reclutamiento_page_ignores_reclutados(): void
    {
        $pokemon = $this->crearPokemon();
        Reclutado::create([
            'nombre' => 'Bulbi',
            'pokemon_id' => $pokemon->id,
            'exp' => ['exp' => 100],
            'obj_equipados' => [],
            'movimientos' => [],
        ]);

        $response = $this->get('/reclutamiento');

        $response->assertOk();
        $this->assertSame([], $response->viewData('reclutables'));
    }

    public function test_recruit_creates_reclutado_and_decrements_cantidad(): void
    {
        $pokemon = $this->crearPokemon();
        $reclutable = Reclutable::create(['pokemon_id' => $pokemon->id, 'cantidad' => 5]);

        $response = $this->postJson('/reclutamiento/recruit', [
            'reclutable_id' => $reclutable->id,
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('reclutables', ['id' => $reclutable->id, 'cantidad' => 4]);
        $this->assertDatabaseHas('reclutados', [
            'pokemon_id' => $pokemon->id,
            'nombre' => null,
            'es_shiny' => false,
        ]);
        $this->assertSame(1, Reclutado::count());
    }

    public function test_recruit_with_cantidad_one_creates_reclutado_and_deletes(): void
    {
        $pokemon = $this->crearPokemon();
        $reclutable = Reclutable::create(['pokemon_id' => $pokemon->id, 'cantidad' => 1]);

        $response = $this->postJson('/reclutamiento/recruit', [
            'reclutable_id' => $reclutable->id,
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseMissing('reclutables', ['id' => $reclutable->id]);
        $this->assertDatabaseHas('reclutados', [
            'pokemon_id' => $pokemon->id,
            'nombre' => null,
            'es_shiny' => false,
        ]);
        $this->assertSame(1, Reclutado::count());
    }

    public function test_recruit_validates_reclutable_id(): void
    {
        $response = $this->postJson('/reclutamiento/recruit', []);

        $response->assertUnprocessable()->assertJsonValidationErrors('reclutable_id');
    }

    public function test_discard_all_awards_candies_by_evolution_phase(): void
    {
        $chain = EvolutionChain::create(['data' => '{"stages": 3}']);
        $bulbasaur = $this->crearPokemon(1, 'bulbasaur', 1, $chain->id);
        $ivysaur = $this->crearPokemon(2, 'ivysaur', 2, $chain->id);
        $venusaur = $this->crearPokemon(3, 'venusaur', 3, $chain->id);

        Reclutable::create(['pokemon_id' => $bulbasaur->id, 'cantidad' => 3]);
        Reclutable::create(['pokemon_id' => $ivysaur->id, 'cantidad' => 2]);
        Reclutable::create(['pokemon_id' => $venusaur->id, 'cantidad' => 1]);

        $response = $this->postJson('/reclutamiento/discard-all');

        // 3 x fase 1 + 2 x fase 2 + 1 x fase 3 = 3 + 4 + 3 = 10
        $response->assertOk()->assertJson([
            'success' => true,
            'candies' => [$chain->id => 10],
        ]);
        $this->assertDatabaseCount('reclutables', 0);
        $this->assertDatabaseHas('caramelos', [
            'evolution_chain_id' => $chain->id,
            'cantidad' => 10,
        ]);
    }

    public function test_discard_all_accumulates_into_existing_caramelo(): void
    {
        $chain = EvolutionChain::create(['data' => '{"stages": 1}']);
        $bulbasaur = $this->crearPokemon(1, 'bulbasaur', 1, $chain->id);
        Caramelo::create(['evolution_chain_id' => $chain->id, 'cantidad' => 5]);
        Reclutable::create(['pokemon_id' => $bulbasaur->id, 'cantidad' => 2]);

        $response = $this->postJson('/reclutamiento/discard-all');

        $response->assertOk();
        $this->assertDatabaseHas('caramelos', [
            'evolution_chain_id' => $chain->id,
            'cantidad' => 7, // 5 + 2 x fase 1
        ]);
        $this->assertSame(1, Caramelo::count());
    }

    public function test_discard_all_handles_multiple_chains_independently(): void
    {
        $chain1 = EvolutionChain::create(['data' => '{"stages": 1}']);
        $chain2 = EvolutionChain::create(['data' => '{"stages": 1}']);
        $bulbasaur = $this->crearPokemon(1, 'bulbasaur', 1, $chain1->id);
        $charmander = $this->crearPokemon(2, 'charmander', 4, $chain2->id);

        Reclutable::create(['pokemon_id' => $bulbasaur->id, 'cantidad' => 3]);
        Reclutable::create(['pokemon_id' => $charmander->id, 'cantidad' => 1]);

        $response = $this->postJson('/reclutamiento/discard-all');

        $response->assertOk()->assertJson([
            'success' => true,
            'candies' => [$chain1->id => 3, $chain2->id => 1],
        ]);
        $this->assertDatabaseHas('caramelos', ['evolution_chain_id' => $chain1->id, 'cantidad' => 3]);
        $this->assertDatabaseHas('caramelos', ['evolution_chain_id' => $chain2->id, 'cantidad' => 1]);
        $this->assertDatabaseCount('reclutables', 0);
    }
}
