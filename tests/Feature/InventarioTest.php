<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TipoEnum;
use App\Models\PlayerInventory;
use App\Models\Pokemon;
use App\Models\PokemonEvolution;
use App\Models\PokemonType;
use App\Models\Reclutable;
use App\Models\Reclutado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Reclutamiento\App\ServicioEvolucion;
use Tests\TestCase;

class InventarioTest extends TestCase
{
    use RefreshDatabase;

    private User $usuarioA;
    private User $usuarioB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuarioA = User::factory()->create();
        $this->usuarioB = User::factory()->create();

        // Cadena charmander → charmeleon (Fuego) para dar-caramelo y evolución.
        $this->crearPokemon(4, 'charmander', 4);
        $this->crearPokemon(5, 'charmeleon', 5);
        PokemonEvolution::create([
            'evolves_from_species_id' => 4,
            'evolved_species_id' => 5,
            'minimum_level' => 16,
        ]);
        PokemonType::create(['pokemon_id' => 5, 'type' => TipoEnum::FIRE, 'slot' => 1]);
    }

    private function crearPokemon(int $id, string $name, int $speciesId): Pokemon
    {
        return Pokemon::create([
            'id' => $id,
            'name' => $name,
            'species_id' => $speciesId,
            'capture_rate' => 45,
            'base_experience' => 62,
            'height' => 6,
            'weight' => 85,
        ]);
    }

    private function crearReclutado(User $usuario, int $expTotal = 33_750): Reclutado
    {
        return Reclutado::create([
            'user_id' => $usuario->id,
            'nombre' => 'Charmander',
            'pokemon_id' => 4,
            'exp' => ['total' => $expTotal],
            'es_shiny' => false,
            'obj_equipados' => [],
            'movimientos' => [],
        ]);
    }

    public function test_dar_caramelo_de_A_no_consume_el_inventario_de_B(): void
    {
        // A tiene 3 caramelos de Fuego; B no tiene ninguno.
        PlayerInventory::create([
            'user_id' => $this->usuarioA->id,
            'item_key' => 'tipo:fuego',
            'cantidad' => 3,
        ]);
        $reclutado = $this->crearReclutado($this->usuarioA);

        $this->actingAs($this->usuarioA)
            ->postJson("/reclutado/{$reclutado->id}/dar-caramelo", ['tipo' => 'Fuego'])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'actual' => 100,
                'caramelos_disponibles' => 2,
            ]);

        $this->assertDatabaseHas('player_inventory', [
            'user_id' => $this->usuarioA->id,
            'item_key' => 'tipo:fuego',
            'cantidad' => 2,
        ]);
        $this->assertDatabaseMissing('player_inventory', [
            'user_id' => $this->usuarioB->id,
            'item_key' => 'tipo:fuego',
        ]);

        // B no tiene caramelos → 422 aunque A sí tenga stock.
        $this->actingAs($this->usuarioB)
            ->postJson("/reclutado/{$reclutado->id}/dar-caramelo", ['tipo' => 'Fuego'])
            ->assertNotFound(); // además: el reclutado es de A → 404 por scope global
    }

    public function test_dar_caramelo_con_stock_agotado_devuelve_422(): void
    {
        // Un solo caramelo: el primero se consume, el segundo ya no puede (observable del lock).
        PlayerInventory::create([
            'user_id' => $this->usuarioA->id,
            'item_key' => 'tipo:fuego',
            'cantidad' => 1,
        ]);
        $reclutado = $this->crearReclutado($this->usuarioA);

        $this->actingAs($this->usuarioA)
            ->postJson("/reclutado/{$reclutado->id}/dar-caramelo", ['tipo' => 'Fuego'])
            ->assertOk()
            ->assertJson(['caramelos_disponibles' => 0]);

        $this->actingAs($this->usuarioA)
            ->postJson("/reclutado/{$reclutado->id}/dar-caramelo", ['tipo' => 'Fuego'])
            ->assertUnprocessable()
            ->assertJson(['error' => 'No hay caramelos de tipo Fuego']);

        // El reclutado solo recibió 100 de exp de tipo (una vez).
        $this->assertSame(100, $reclutado->fresh()->exp->expTipo('Fuego'));
    }

    public function test_requisitos_muestran_caramelos_solo_del_usuario_autenticado(): void
    {
        PlayerInventory::create([
            'user_id' => $this->usuarioA->id,
            'item_key' => 'tipo:fuego',
            'cantidad' => 5,
        ]);
        PlayerInventory::create([
            'user_id' => $this->usuarioB->id,
            'item_key' => 'tipo:fuego',
            'cantidad' => 9,
        ]);
        $reclutadoA = $this->crearReclutado($this->usuarioA);
        $reclutadoB = $this->crearReclutado($this->usuarioB);

        $requisitosA = ServicioEvolucion::requisitos($reclutadoA, $this->usuarioA->id);
        $requisitosB = ServicioEvolucion::requisitos($reclutadoB, $this->usuarioB->id);

        $this->assertSame(5, $requisitosA[0]['caramelosDisponibles']);
        $this->assertSame(9, $requisitosB[0]['caramelosDisponibles']);
    }

    public function test_discard_de_A_otorga_caramelos_solo_al_inventario_de_A(): void
    {
        $pokemon = $this->crearPokemon(1, 'bulbasaur', 1);
        $this->crearPokemon(2, 'ivysaur', 2);
        $this->crearPokemon(3, 'venusaur', 3);
        Pokemon::whereIn('id', [1, 2, 3])->update(['evolution_chain_id' => 51]);

        Reclutable::create([
            'user_id' => $this->usuarioA->id,
            'pokemon_id' => $pokemon->id,
            'cantidad' => 2,
        ]);
        Reclutable::create([
            'user_id' => $this->usuarioB->id,
            'pokemon_id' => $pokemon->id,
            'cantidad' => 4,
        ]);

        $this->actingAs($this->usuarioA)
            ->postJson('/reclutamiento/discard', [
                'reclutable_id' => Reclutable::withoutUserScope()
                    ->where('user_id', $this->usuarioA->id)
                    ->firstOrFail()->id,
                'cantidad' => 2,
            ])
            ->assertOk();

        // A: fase 1 × 2 descartados = 2 caramelos de la familia 51.
        $this->assertDatabaseHas('player_inventory', [
            'user_id' => $this->usuarioA->id,
            'item_key' => 'familia:51',
            'cantidad' => 2,
        ]);
        // B no recibió nada y conserva sus reclutables.
        $this->assertDatabaseMissing('player_inventory', ['user_id' => $this->usuarioB->id]);
        $this->assertDatabaseHas('reclutables', [
            'user_id' => $this->usuarioB->id,
            'pokemon_id' => $pokemon->id,
            'cantidad' => 4,
        ]);
    }

    public function test_discard_all_solo_borra_los_reclutables_del_usuario(): void
    {
        $pokemon = $this->crearPokemon(1, 'bulbasaur', 1);
        Pokemon::where('id', 1)->update(['evolution_chain_id' => 51]);

        Reclutable::create(['user_id' => $this->usuarioA->id, 'pokemon_id' => $pokemon->id, 'cantidad' => 2]);
        Reclutable::create(['user_id' => $this->usuarioB->id, 'pokemon_id' => $pokemon->id, 'cantidad' => 4]);

        $this->actingAs($this->usuarioA)->postJson('/reclutamiento/discard-all')->assertOk();

        $this->assertDatabaseMissing('reclutables', ['user_id' => $this->usuarioA->id]);
        $this->assertDatabaseHas('reclutables', [
            'user_id' => $this->usuarioB->id,
            'pokemon_id' => $pokemon->id,
            'cantidad' => 4,
        ]);
    }

    public function test_recruit_no_permite_reclutar_un_reclutable_de_otro_usuario(): void
    {
        $pokemon = $this->crearPokemon(1, 'bulbasaur', 1);
        $reclutableB = Reclutable::create([
            'user_id' => $this->usuarioB->id,
            'pokemon_id' => $pokemon->id,
            'cantidad' => 1,
        ]);

        $this->actingAs($this->usuarioA)
            ->postJson('/reclutamiento/recruit', ['reclutable_id' => $reclutableB->id])
            ->assertNotFound();

        // No se crea ningún reclutado y el reclutable de B queda intacto.
        $this->assertSame(0, Reclutado::withoutUserScope()->count());
        $this->assertDatabaseHas('reclutables', ['id' => $reclutableB->id, 'cantidad' => 1]);
    }
}
