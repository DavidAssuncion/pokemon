<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Caramelo;
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

    public function test_recruit_marks_pokedex_as_seen_and_captured(): void
    {
        $pokemon = $this->crearPokemon();
        $reclutable = Reclutable::create(['pokemon_id' => $pokemon->id, 'cantidad' => 1]);

        $response = $this->postJson('/reclutamiento/recruit', [
            'reclutable_id' => $reclutable->id,
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('pokedex', [
            'pokemon_id' => $pokemon->id,
            'visto' => true,
            'atrapado' => true,
        ]);
    }

    public function test_discard_decrements_cantidad_and_awards_candies(): void
    {
        $bulbasaur = $this->crearPokemon(1, 'bulbasaur', 1, 51);
        $this->crearPokemon(2, 'ivysaur', 2, 51);
        $this->crearPokemon(3, 'venusaur', 3, 51);
        $reclutable = Reclutable::create(['pokemon_id' => $bulbasaur->id, 'cantidad' => 5]);

        $response = $this->postJson('/reclutamiento/discard', [
            'reclutable_id' => $reclutable->id,
            'cantidad' => 2,
        ]);

        // fase 1 × 2 descartados = 2 caramelos
        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('reclutables', ['id' => $reclutable->id, 'cantidad' => 3]);
        $this->assertDatabaseHas('caramelos', [
            'evolution_chain_id' => 51,
            'cantidad' => 2,
        ]);
    }

    public function test_discard_with_cantidad_above_available_clamps_to_available(): void
    {
        $bulbasaur = $this->crearPokemon(1, 'bulbasaur', 1, 51);
        $reclutable = Reclutable::create(['pokemon_id' => $bulbasaur->id, 'cantidad' => 3]);

        $response = $this->postJson('/reclutamiento/discard', [
            'reclutable_id' => $reclutable->id,
            'cantidad' => 99,
        ]);

        // Clamp a 3 → igual a la cantidad disponible → registro eliminado, fase 1 × 3 caramelos
        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseMissing('reclutables', ['id' => $reclutable->id]);
        $this->assertDatabaseHas('caramelos', [
            'evolution_chain_id' => 51,
            'cantidad' => 3,
        ]);
    }

    public function test_discard_with_full_cantidad_deletes_record(): void
    {
        $bulbasaur = $this->crearPokemon(1, 'bulbasaur', 1, 51);
        $reclutable = Reclutable::create(['pokemon_id' => $bulbasaur->id, 'cantidad' => 2]);

        $response = $this->postJson('/reclutamiento/discard', [
            'reclutable_id' => $reclutable->id,
            'cantidad' => 2,
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseMissing('reclutables', ['id' => $reclutable->id]);
        $this->assertDatabaseHas('caramelos', [
            'evolution_chain_id' => 51,
            'cantidad' => 2,
        ]);
    }

    public function test_discard_validates_cantidad(): void
    {
        $pokemon = $this->crearPokemon();
        $reclutable = Reclutable::create(['pokemon_id' => $pokemon->id, 'cantidad' => 3]);

        $response = $this->postJson('/reclutamiento/discard', [
            'reclutable_id' => $reclutable->id,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('cantidad');
        $this->assertDatabaseHas('reclutables', ['id' => $reclutable->id, 'cantidad' => 3]);
    }

    public function test_discard_all_awards_candies_by_evolution_phase(): void
    {
        $bulbasaur = $this->crearPokemon(1, 'bulbasaur', 1, 51);
        $ivysaur = $this->crearPokemon(2, 'ivysaur', 2, 51);
        $venusaur = $this->crearPokemon(3, 'venusaur', 3, 51);

        Reclutable::create(['pokemon_id' => $bulbasaur->id, 'cantidad' => 3]);
        Reclutable::create(['pokemon_id' => $ivysaur->id, 'cantidad' => 2]);
        Reclutable::create(['pokemon_id' => $venusaur->id, 'cantidad' => 1]);

        $response = $this->postJson('/reclutamiento/discard-all');

        // 3 x fase 1 + 2 x fase 2 + 1 x fase 3 = 3 + 4 + 3 = 10
        $response->assertOk()->assertJson([
            'success' => true,
            'candies' => [51 => 10],
        ]);
        $this->assertDatabaseCount('reclutables', 0);
        $this->assertDatabaseHas('caramelos', [
            'evolution_chain_id' => 51,
            'cantidad' => 10,
        ]);
    }

    public function test_discard_all_accumulates_into_existing_caramelo(): void
    {
        $bulbasaur = $this->crearPokemon(1, 'bulbasaur', 1, 51);
        Caramelo::create(['evolution_chain_id' => 51, 'cantidad' => 5]);
        Reclutable::create(['pokemon_id' => $bulbasaur->id, 'cantidad' => 2]);

        $response = $this->postJson('/reclutamiento/discard-all');

        $response->assertOk();
        $this->assertDatabaseHas('caramelos', [
            'evolution_chain_id' => 51,
            'cantidad' => 7, // 5 + 2 x fase 1
        ]);
        $this->assertSame(1, Caramelo::count());
    }

    public function test_discard_all_handles_multiple_chains_independently(): void
    {
        $bulbasaur = $this->crearPokemon(1, 'bulbasaur', 1, 51);
        $charmander = $this->crearPokemon(2, 'charmander', 4, 52);

        Reclutable::create(['pokemon_id' => $bulbasaur->id, 'cantidad' => 3]);
        Reclutable::create(['pokemon_id' => $charmander->id, 'cantidad' => 1]);

        $response = $this->postJson('/reclutamiento/discard-all');

        $response->assertOk()->assertJson([
            'success' => true,
            'candies' => [51 => 3, 52 => 1],
        ]);
        $this->assertDatabaseHas('caramelos', ['evolution_chain_id' => 51, 'cantidad' => 3]);
        $this->assertDatabaseHas('caramelos', ['evolution_chain_id' => 52, 'cantidad' => 1]);
        $this->assertDatabaseCount('reclutables', 0);
    }
}
