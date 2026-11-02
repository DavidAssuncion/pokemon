<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\PlayerInventory;
use App\Models\Pokemon;
use App\Models\Province;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReclutadoLiberarTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    private function createPokemon(int $id): Pokemon
    {
        return Pokemon::create([
            'id' => $id,
            'name' => 'pokemon-'.$id,
            'species_id' => $id,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);
    }

    private function createReclutado(int $userId, int $pokemonId, string $nombre): Reclutado
    {
        return Reclutado::create([
            'user_id' => $userId,
            'nombre' => $nombre,
            'pokemon_id' => $pokemonId,
            'exp' => ['total' => 100],
            'es_shiny' => false,
            'obj_equipados' => [],
            'movimientos' => [],
        ]);
    }

    private function createExploracionActiva(int $userId, int $reclutadoId): void
    {
        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => $province->id]);
        ExploracionActiva::create([
            'user_id' => $userId,
            'reclutado_id' => $reclutadoId,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
            'duracion_horas' => 2,
            'inicio_exploracion' => now(),
            'llegada_destino' => now()->addHour(),
            'regreso' => null,
        ]);
    }

    public function test_liberar_reclutado_sin_equipo_devuelve_success(): void
    {
        $user = $this->actingAsUser();
        $pokemon = $this->createPokemon(1);
        $reclutado = $this->createReclutado($user->id, $pokemon->id, 'Bulbi');

        $response = $this->deleteJson("/reclutado/{$reclutado->id}");

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseMissing('reclutados', ['id' => $reclutado->id]);
    }

    public function test_liberar_reclutado_asignado_a_equipo_inactivo_borra_member_y_reclutado(): void
    {
        $user = $this->actingAsUser();
        $team = Team::create(['name' => 'Inactivo', 'user_id' => $user->id]);
        $pokemon = $this->createPokemon(1);
        $reclutado = $this->createReclutado($user->id, $pokemon->id, 'Bulbi');
        $member = TeamMember::create([
            'team_id' => $team->id,
            'pokemon_id' => $reclutado->id,
            'slot' => 1,
            'behavior' => 'VANGUARDIA',
        ]);

        $response = $this->deleteJson("/reclutado/{$reclutado->id}");

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseMissing('team_members', ['id' => $member->id]);
        $this->assertDatabaseMissing('reclutados', ['id' => $reclutado->id]);
    }

    public function test_liberar_reclutado_en_exploracion_devuelve_422(): void
    {
        $user = $this->actingAsUser();
        $team = Team::create(['name' => 'Explorando', 'user_id' => $user->id]);
        $pokemon = $this->createPokemon(1);
        $reclutado = $this->createReclutado($user->id, $pokemon->id, 'Bulbi');
        $member = TeamMember::create([
            'team_id' => $team->id,
            'pokemon_id' => $reclutado->id,
            'slot' => 1,
            'behavior' => 'VANGUARDIA',
        ]);
        $this->createExploracionActiva($user->id, $reclutado->id);

        $response = $this->deleteJson("/reclutado/{$reclutado->id}");

        $response->assertUnprocessable()
            ->assertJson(['error' => 'No se puede liberar un Pokémon en exploración']);
        $this->assertDatabaseHas('team_members', ['id' => $member->id]);
        $this->assertDatabaseHas('reclutados', ['id' => $reclutado->id]);
    }

    public function test_companero_del_mismo_equipo_puede_liberarse_aunque_otro_explore(): void
    {
        $user = $this->actingAsUser();
        $team = Team::create(['name' => 'Explorando', 'user_id' => $user->id]);
        $pokemonExplorador = $this->createPokemon(1);
        $pokemonCompanero = $this->createPokemon(2);
        $explorador = $this->createReclutado($user->id, $pokemonExplorador->id, 'Explorador');
        $companero = $this->createReclutado($user->id, $pokemonCompanero->id, 'Companero');
        TeamMember::create([
            'team_id' => $team->id,
            'pokemon_id' => $explorador->id,
            'slot' => 1,
            'behavior' => 'VANGUARDIA',
        ]);
        $memberCompanero = TeamMember::create([
            'team_id' => $team->id,
            'pokemon_id' => $companero->id,
            'slot' => 2,
            'behavior' => 'COMBATIENTE',
        ]);
        $this->createExploracionActiva($user->id, $explorador->id);

        $response = $this->deleteJson("/reclutado/{$companero->id}");

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseMissing('team_members', ['id' => $memberCompanero->id]);
        $this->assertDatabaseMissing('reclutados', ['id' => $companero->id]);
        $this->assertDatabaseHas('reclutados', ['id' => $explorador->id]);
    }

    public function test_companero_del_mismo_equipo_puede_recibir_caramelos_ev_aunque_otro_explore(): void
    {
        $user = $this->actingAsUser();
        $team = Team::create(['name' => 'Explorando', 'user_id' => $user->id]);
        $pokemonExplorador = $this->createPokemon(1);
        $pokemonCompanero = $this->createPokemon(2);
        $explorador = $this->createReclutado($user->id, $pokemonExplorador->id, 'Explorador');
        $companero = $this->createReclutado($user->id, $pokemonCompanero->id, 'Companero');
        TeamMember::create([
            'team_id' => $team->id,
            'pokemon_id' => $explorador->id,
            'slot' => 1,
            'behavior' => 'VANGUARDIA',
        ]);
        TeamMember::create([
            'team_id' => $team->id,
            'pokemon_id' => $companero->id,
            'slot' => 2,
            'behavior' => 'COMBATIENTE',
        ]);
        $this->createExploracionActiva($user->id, $explorador->id);
        PlayerInventory::create([
            'user_id' => $user->id,
            'item_key' => 'ev:1',
            'cantidad' => 1,
        ]);

        $response = $this->postJson("/reclutado/{$companero->id}/dar-evs", ['stat' => 'hp']);

        $response->assertOk()->assertJson(['success' => true, 'actual' => 10]);
    }

    public function test_liberar_reclutado_de_otro_usuario_devuelve_404(): void
    {
        $usuarioA = User::factory()->create();
        $usuarioB = User::factory()->create();
        $pokemon = $this->createPokemon(1);
        $reclutadoB = $this->createReclutado($usuarioB->id, $pokemon->id, 'B1');

        $this->actingAs($usuarioA);

        $response = $this->deleteJson("/reclutado/{$reclutadoB->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('reclutados', ['id' => $reclutadoB->id]);
    }
}
