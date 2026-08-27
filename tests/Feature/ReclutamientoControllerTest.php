<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Pokemon;
use App\Models\Reclutable;
use App\Models\Reclutado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReclutamientoControllerTest extends TestCase
{
    use RefreshDatabase;

    private function crearPokemon(int $id = 1, string $name = 'bulbasaur'): Pokemon
    {
        return Pokemon::create([
            'id' => $id,
            'name' => $name,
            'species_id' => $id,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
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

    public function test_recruit_decrements_cantidad(): void
    {
        $pokemon = $this->crearPokemon();
        $reclutable = Reclutable::create(['pokemon_id' => $pokemon->id, 'cantidad' => 5]);

        $response = $this->postJson('/reclutamiento/recruit', [
            'reclutable_id' => $reclutable->id,
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('reclutables', ['id' => $reclutable->id, 'cantidad' => 4]);
    }

    public function test_recruit_deletes_row_when_cantidad_reaches_zero(): void
    {
        $pokemon = $this->crearPokemon();
        $reclutable = Reclutable::create(['pokemon_id' => $pokemon->id, 'cantidad' => 1]);

        $response = $this->postJson('/reclutamiento/recruit', [
            'reclutable_id' => $reclutable->id,
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseMissing('reclutables', ['id' => $reclutable->id]);
    }

    public function test_recruit_validates_reclutable_id(): void
    {
        $response = $this->postJson('/reclutamiento/recruit', []);

        $response->assertUnprocessable()->assertJsonValidationErrors('reclutable_id');
    }

    public function test_discard_all_deletes_every_reclutable(): void
    {
        $pokemon1 = $this->crearPokemon(1, 'bulbasaur');
        $pokemon2 = $this->crearPokemon(2, 'ivysaur');
        Reclutable::create(['pokemon_id' => $pokemon1->id, 'cantidad' => 3]);
        Reclutable::create(['pokemon_id' => $pokemon2->id, 'cantidad' => 2]);

        $response = $this->postJson('/reclutamiento/discard-all');

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseCount('reclutables', 0);
    }
}
