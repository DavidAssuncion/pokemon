<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatEnum;
use App\Models\PlayerInventory;
use App\Models\Pokemon;
use App\Models\Reclutado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Granjas\App\AplicarEvsFamilia;
use Tests\TestCase;

class TopeEvTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    private function createPokemon(int $id, int $evolutionChainId = 1): Pokemon
    {
        return Pokemon::create([
            'id' => $id,
            'name' => 'pokemon-'.$id,
            'species_id' => $id,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
            'evolution_chain_id' => $evolutionChainId,
        ]);
    }

    /**
     * @param  array<string, float|int>  $evs
     */
    private function createReclutado(int $userId, int $pokemonId, array $evs): Reclutado
    {
        return Reclutado::create([
            'user_id' => $userId,
            'nombre' => 'pokemon-'.$pokemonId,
            'pokemon_id' => $pokemonId,
            'exp' => ['total' => 100],
            'es_shiny' => false,
            'obj_equipados' => [],
            'movimientos' => [],
            'evs' => $evs,
        ]);
    }

    private function darEvs(Reclutado $reclutado, int $cantidad): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/reclutado/{$reclutado->id}/dar-evs", [
            'stat' => 'hp',
            'cantidad' => $cantidad,
        ]);
    }

    public function test_dar_evs_desde_250_con_un_caramelo_recorta_al_tope(): void
    {
        $user = $this->actingAsUser();
        $pokemon = $this->createPokemon(1);
        $reclutado = $this->createReclutado($user->id, $pokemon->id, ['hp' => 250]);
        PlayerInventory::create([
            'user_id' => $user->id,
            'item_key' => 'ev:1',
            'cantidad' => 1,
        ]);

        $response = $this->darEvs($reclutado, 1);

        $response->assertOk()->assertJson([
            'success' => true,
            'actual' => 255,
            'cantidad_consumida' => 1,
        ]);

        $this->assertSame(255.0, $reclutado->refresh()->evs->valorDe('hp'));
    }

    public function test_dar_evs_en_el_tope_devuelve_422_sin_consumir(): void
    {
        $user = $this->actingAsUser();
        $pokemon = $this->createPokemon(1);
        $reclutado = $this->createReclutado($user->id, $pokemon->id, ['hp' => 255]);
        $inventario = PlayerInventory::create([
            'user_id' => $user->id,
            'item_key' => 'ev:1',
            'cantidad' => 1,
        ]);

        $response = $this->darEvs($reclutado, 1);

        $response->assertStatus(422)->assertJson([
            'error' => 'Ese stat ya está al máximo (255)',
        ]);

        $this->assertSame(1, $inventario->refresh()->cantidad);
        $this->assertSame(255.0, $reclutado->refresh()->evs->valorDe('hp'));
    }

    public function test_dar_evs_no_reduce_un_legado_por_encima_del_tope(): void
    {
        $user = $this->actingAsUser();
        $pokemon = $this->createPokemon(1);
        $reclutado = $this->createReclutado($user->id, $pokemon->id, ['hp' => 300]);
        PlayerInventory::create([
            'user_id' => $user->id,
            'item_key' => 'ev:1',
            'cantidad' => 1,
        ]);

        $response = $this->darEvs($reclutado, 1);

        $response->assertStatus(422);
        $this->assertSame(300.0, $reclutado->refresh()->evs->valorDe('hp'));
    }

    public function test_aplicar_evs_familia_recorta_al_tope(): void
    {
        $user = User::factory()->create();
        $pokemon = $this->createPokemon(1, evolutionChainId: 7);
        $reclutado = $this->createReclutado($user->id, $pokemon->id, ['hp' => 250]);

        (new AplicarEvsFamilia())->aplicar($user->id, 7, StatEnum::HP, 10);

        $this->assertSame(255.0, $reclutado->refresh()->evs->valorDe('hp'));
    }

    public function test_aplicar_evs_familia_conserva_legado_por_encima_del_tope(): void
    {
        $user = User::factory()->create();
        $pokemon = $this->createPokemon(1, evolutionChainId: 7);
        $reclutado = $this->createReclutado($user->id, $pokemon->id, ['hp' => 300]);

        (new AplicarEvsFamilia())->aplicar($user->id, 7, StatEnum::HP, 10);

        $this->assertSame(300.0, $reclutado->refresh()->evs->valorDe('hp'));
    }
}
