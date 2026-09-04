<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\Province;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquiposControllerTest extends TestCase
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
            'exp' => ['exp' => 100],
            'obj_equipados' => [],
            'movimientos' => [],
        ]);
    }

    public function test_store_creates_team(): void
    {
        $this->actingAsUser();

        $response = $this->post('/teams', ['name' => 'Mewtwo Fighters']);

        $response->assertRedirect();
        $this->assertDatabaseHas('teams', ['name' => 'Mewtwo Fighters']);
    }

    public function test_store_redirects_back(): void
    {
        $this->actingAsUser();

        $response = $this->post('/teams', ['name' => 'Team Rocket']);

        $response->assertRedirect();
    }

    public function test_store_validates_name_is_required(): void
    {
        $this->actingAsUser();

        $response = $this->post('/teams', []);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('teams', 0);
    }

    public function test_store_json_assigns_team_to_authenticated_user(): void
    {
        $user = $this->actingAsUser();

        $response = $this->postJson('/teams', ['name' => 'JSON Team']);

        $response->assertOk();
        $this->assertDatabaseHas('teams', ['name' => 'JSON Team', 'user_id' => $user->id]);
    }

    public function test_destroy_removes_team(): void
    {
        $user = $this->actingAsUser();
        $team = Team::create(['name' => 'Disposable', 'user_id' => $user->id]);

        $response = $this->delete("/teams/{$team->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
    }

    public function test_add_member_creates_team_member(): void
    {
        $user = $this->actingAsUser();
        $team = Team::create(['name' => 'Alpha', 'user_id' => $user->id]);
        $pokemon = $this->createPokemon(1);
        $reclutado = $this->createReclutado($user->id, $pokemon->id, 'Bulbi');

        $response = $this->post('/teams/add-member', [
            'team_id' => $team->id,
            'reclutado_id' => $reclutado->id,
            'slot' => 1,
            'behavior' => 'VANGUARDIA',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('team_members', [
            'team_id' => $team->id,
            'pokemon_id' => $reclutado->id,
            'slot' => 1,
            'behavior' => 'VANGUARDIA',
        ]);
    }

    public function test_add_member_validates_required_fields(): void
    {
        $this->actingAsUser();

        $response = $this->post('/teams/add-member', []);

        $response->assertSessionHasErrors(['team_id', 'reclutado_id', 'slot', 'behavior']);
    }

    public function test_remove_member_deletes_team_member(): void
    {
        $user = $this->actingAsUser();
        $team = Team::create(['name' => 'Alpha', 'user_id' => $user->id]);
        $pokemon = $this->createPokemon(1);
        $reclutado = $this->createReclutado($user->id, $pokemon->id, 'Bulbi');
        $member = TeamMember::create([
            'team_id' => $team->id,
            'pokemon_id' => $reclutado->id,
            'slot' => 1,
            'behavior' => 'VANGUARDIA',
        ]);

        $response = $this->post('/teams/remove-member', [
            'member_id' => $member->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('team_members', ['id' => $member->id]);
    }

    public function test_add_member_json_returns_member_payload(): void
    {
        $user = $this->actingAsUser();
        $team = Team::create(['name' => 'Alpha', 'user_id' => $user->id]);
        $pokemon = $this->createPokemon(1);
        $reclutado = $this->createReclutado($user->id, $pokemon->id, 'Bulbi');

        $response = $this->postJson('/teams/add-member', [
            'team_id' => $team->id,
            'reclutado_id' => $reclutado->id,
            'slot' => 1,
            'behavior' => 'VANGUARDIA',
        ]);

        $response->assertOk()->assertJson([
            'member' => [
                'team_id' => $team->id,
                'pokemon_id' => $reclutado->id,
                'slot' => 1,
            ],
        ]);
        $this->assertIsInt($response->json('member.id'));
        $this->assertDatabaseHas('team_members', [
            'team_id' => $team->id,
            'pokemon_id' => $reclutado->id,
            'slot' => 1,
        ]);
    }

    public function test_add_member_json_rejects_slot_occupied(): void
    {
        $user = $this->actingAsUser();
        $team = Team::create(['name' => 'Alpha', 'user_id' => $user->id]);
        $this->createPokemon(1);
        $this->createPokemon(2);
        $reclutado1 = $this->createReclutado($user->id, 1, 'Bulbi');
        $reclutado2 = $this->createReclutado($user->id, 2, 'Char');
        TeamMember::create([
            'team_id' => $team->id,
            'pokemon_id' => $reclutado1->id,
            'slot' => 1,
            'behavior' => 'VANGUARDIA',
        ]);

        $response = $this->postJson('/teams/add-member', [
            'team_id' => $team->id,
            'reclutado_id' => $reclutado2->id,
            'slot' => 1,
            'behavior' => 'COMBATIENTE',
        ]);

        $response->assertStatus(422)->assertJson(['error' => 'Slot ya ocupado']);
    }

    public function test_add_member_json_rejects_pokemon_already_in_team(): void
    {
        $user = $this->actingAsUser();
        $team1 = Team::create(['name' => 'Alpha', 'user_id' => $user->id]);
        $team2 = Team::create(['name' => 'Beta', 'user_id' => $user->id]);
        $pokemon = $this->createPokemon(1);
        $reclutado = $this->createReclutado($user->id, $pokemon->id, 'Bulbi');
        TeamMember::create([
            'team_id' => $team1->id,
            'pokemon_id' => $reclutado->id,
            'slot' => 1,
            'behavior' => 'VANGUARDIA',
        ]);

        $response = $this->postJson('/teams/add-member', [
            'team_id' => $team2->id,
            'reclutado_id' => $reclutado->id,
            'slot' => 2,
            'behavior' => 'RASTREADOR',
        ]);

        $response->assertStatus(422)->assertJson(['error' => 'Pokémon ya está en un equipo']);
    }

    public function test_destroy_json_rejects_team_with_active_exploration(): void
    {
        $user = $this->actingAsUser();
        $team = Team::create(['name' => 'Exploring', 'user_id' => $user->id]);
        $pokemon = $this->createPokemon(1);
        $reclutado = $this->createReclutado($user->id, $pokemon->id, 'Explorador');
        TeamMember::create([
            'team_id' => $team->id,
            'pokemon_id' => $reclutado->id,
            'slot' => 1,
            'behavior' => 'VANGUARDIA',
        ]);
        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => $province->id]);
        ExploracionActiva::create([
            'user_id' => $user->id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
            'duracion_horas' => 2,
            'inicio_exploracion' => now(),
            'llegada_destino' => now()->addHour(),
            'regreso' => null,
        ]);

        $response = $this->deleteJson("/teams/{$team->id}");

        $response->assertStatus(422)
            ->assertJson(['error' => 'No se puede borrar un equipo con exploraciones activas']);
        $this->assertDatabaseHas('teams', ['id' => $team->id]);
    }

    public function test_remove_member_json_returns_success(): void
    {
        $user = $this->actingAsUser();
        $team = Team::create(['name' => 'Alpha', 'user_id' => $user->id]);
        $pokemon = $this->createPokemon(1);
        $reclutado = $this->createReclutado($user->id, $pokemon->id, 'Bulbi');
        $member = TeamMember::create([
            'team_id' => $team->id,
            'pokemon_id' => $reclutado->id,
            'slot' => 1,
            'behavior' => 'VANGUARDIA',
        ]);

        $response = $this->postJson('/teams/remove-member', [
            'member_id' => $member->id,
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseMissing('team_members', ['id' => $member->id]);
    }

    public function test_reclutados_redirects_to_equipos(): void
    {
        $this->actingAsUser();

        $response = $this->get('/reclutados');

        $response->assertRedirect('/equipos');
    }

    public function test_usuario_a_no_puede_editar_equipo_de_b(): void
    {
        $usuarioA = User::factory()->create();
        $usuarioB = User::factory()->create();
        $teamB = Team::create(['name' => 'Equipo B', 'user_id' => $usuarioB->id]);

        $this->actingAs($usuarioA);

        $this->put("/teams/{$teamB->id}", ['name' => 'Hackeado'])->assertNotFound();
        $this->assertDatabaseHas('teams', ['id' => $teamB->id, 'name' => 'Equipo B']);
    }

    public function test_usuario_a_no_puede_eliminar_equipo_de_b(): void
    {
        $usuarioA = User::factory()->create();
        $usuarioB = User::factory()->create();
        $teamB = Team::create(['name' => 'Equipo B', 'user_id' => $usuarioB->id]);

        $this->actingAs($usuarioA);

        $this->deleteJson("/teams/{$teamB->id}")->assertNotFound();
        $this->assertDatabaseHas('teams', ['id' => $teamB->id]);
    }

    public function test_usuario_a_no_puede_anadir_miembro_a_equipo_de_b(): void
    {
        $usuarioA = User::factory()->create();
        $usuarioB = User::factory()->create();
        $pokemon = $this->createPokemon(1);
        $teamB = Team::create(['name' => 'Equipo B', 'user_id' => $usuarioB->id]);
        // El reclutado es de A (pasa la validación de propiedad); el equipo es de B:
        // Team::findOrFail con el global scope debe devolver 404.
        $reclutadoA = $this->createReclutado($usuarioA->id, $pokemon->id, 'A1');

        $this->actingAs($usuarioA);

        $this->post('/teams/add-member', [
            'team_id' => $teamB->id,
            'reclutado_id' => $reclutadoA->id,
            'slot' => 1,
            'behavior' => 'VANGUARDIA',
        ])->assertNotFound();

        $this->assertDatabaseCount('team_members', 0);
    }

    public function test_usuario_a_no_puede_anadir_reclutado_de_b_a_su_equipo(): void
    {
        $usuarioA = User::factory()->create();
        $usuarioB = User::factory()->create();
        $pokemon = $this->createPokemon(1);
        $teamA = Team::create(['name' => 'Equipo A', 'user_id' => $usuarioA->id]);
        $reclutadoB = $this->createReclutado($usuarioB->id, $pokemon->id, 'B1');

        $this->actingAs($usuarioA);

        $response = $this->post('/teams/add-member', [
            'team_id' => $teamA->id,
            'reclutado_id' => $reclutadoB->id,
            'slot' => 1,
            'behavior' => 'VANGUARDIA',
        ]);

        $response->assertSessionHasErrors('reclutado_id');
        $this->assertDatabaseCount('team_members', 0);
    }

    public function test_usuario_a_no_puede_quitar_miembro_de_equipo_de_b(): void
    {
        $usuarioA = User::factory()->create();
        $usuarioB = User::factory()->create();
        $pokemon = $this->createPokemon(1);
        $teamB = Team::create(['name' => 'Equipo B', 'user_id' => $usuarioB->id]);
        $reclutadoB = $this->createReclutado($usuarioB->id, $pokemon->id, 'B1');
        $member = TeamMember::create([
            'team_id' => $teamB->id,
            'pokemon_id' => $reclutadoB->id,
            'slot' => 1,
            'behavior' => 'VANGUARDIA',
        ]);

        $this->actingAs($usuarioA);

        $this->post('/teams/remove-member', ['member_id' => $member->id])->assertNotFound();
        $this->assertDatabaseHas('team_members', ['id' => $member->id]);
    }

    // ──── updateMemberRole (Task B) ────────────────────────────────────────

    public function test_update_member_role_valido_actualiza_behavior(): void
    {
        $user = $this->actingAsUser();
        $team = Team::create(['name' => 'Test', 'user_id' => $user->id]);
        $pokemon = $this->createPokemon(1);
        $reclutado = $this->createReclutado($user->id, $pokemon->id, 'Bulbi');
        $member = TeamMember::create([
            'team_id' => $team->id,
            'pokemon_id' => $reclutado->id,
            'slot' => 1,
            'behavior' => 'VANGUARDIA',
        ]);

        $response = $this->patchJson("/teams/member/{$member->id}/role", [
            'behavior' => 'COMBATIENTE',
        ]);

        $response->assertOk()
            ->assertJson(['member' => ['id' => $member->id, 'behavior' => 'COMBATIENTE']]);
        $this->assertDatabaseHas('team_members', [
            'id' => $member->id,
            'behavior' => 'COMBATIENTE',
        ]);
    }

    public function test_update_member_role_behavior_invalido_devuelve_422(): void
    {
        $user = $this->actingAsUser();
        $team = Team::create(['name' => 'Test', 'user_id' => $user->id]);
        $pokemon = $this->createPokemon(1);
        $reclutado = $this->createReclutado($user->id, $pokemon->id, 'Bulbi');
        $member = TeamMember::create([
            'team_id' => $team->id,
            'pokemon_id' => $reclutado->id,
            'slot' => 1,
            'behavior' => 'VANGUARDIA',
        ]);

        $response = $this->patchJson("/teams/member/{$member->id}/role", [
            'behavior' => 'INVALIDO',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('team_members', ['id' => $member->id, 'behavior' => 'VANGUARDIA']);
    }

    public function test_update_member_role_miembro_de_otro_usuario_devuelve_404(): void
    {
        $usuarioA = User::factory()->create();
        $usuarioB = User::factory()->create();
        $pokemon = $this->createPokemon(1);
        $teamB = Team::create(['name' => 'B', 'user_id' => $usuarioB->id]);
        $reclutadoB = $this->createReclutado($usuarioB->id, $pokemon->id, 'B1');
        $member = TeamMember::create([
            'team_id' => $teamB->id,
            'pokemon_id' => $reclutadoB->id,
            'slot' => 1,
            'behavior' => 'VANGUARDIA',
        ]);

        $this->actingAs($usuarioA);

        $response = $this->patchJson("/teams/member/{$member->id}/role", [
            'behavior' => 'COMBATIENTE',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseHas('team_members', ['id' => $member->id, 'behavior' => 'VANGUARDIA']);
    }

    // ──── updateMemberRole vía POST /teams/update-member-role (contrato frontend) ──

    public function test_update_member_role_via_post_valido_actualiza_behavior(): void
    {
        $user = $this->actingAsUser();
        $team = Team::create(['name' => 'Test', 'user_id' => $user->id]);
        $pokemon = $this->createPokemon(1);
        $reclutado = $this->createReclutado($user->id, $pokemon->id, 'Bulbi');
        $member = TeamMember::create([
            'team_id' => $team->id,
            'pokemon_id' => $reclutado->id,
            'slot' => 1,
            'behavior' => 'VANGUARDIA',
        ]);

        $response = $this->postJson('/teams/update-member-role', [
            'member_id' => $member->id,
            'behavior' => 'RASTREADOR',
        ]);

        $response->assertOk()
            ->assertJson(['member' => ['id' => $member->id, 'behavior' => 'RASTREADOR']]);
        $this->assertDatabaseHas('team_members', [
            'id' => $member->id,
            'behavior' => 'RASTREADOR',
        ]);
    }

    public function test_update_member_role_via_post_behavior_invalido_devuelve_422(): void
    {
        $user = $this->actingAsUser();
        $team = Team::create(['name' => 'Test', 'user_id' => $user->id]);
        $pokemon = $this->createPokemon(1);
        $reclutado = $this->createReclutado($user->id, $pokemon->id, 'Bulbi');
        $member = TeamMember::create([
            'team_id' => $team->id,
            'pokemon_id' => $reclutado->id,
            'slot' => 1,
            'behavior' => 'VANGUARDIA',
        ]);

        $response = $this->postJson('/teams/update-member-role', [
            'member_id' => $member->id,
            'behavior' => 'INVALIDO',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('team_members', ['id' => $member->id, 'behavior' => 'VANGUARDIA']);
    }

    public function test_update_member_role_via_post_miembro_ajeno_devuelve_404(): void
    {
        $usuarioA = User::factory()->create();
        $usuarioB = User::factory()->create();
        $pokemon = $this->createPokemon(1);
        $teamB = Team::create(['name' => 'B', 'user_id' => $usuarioB->id]);
        $reclutadoB = $this->createReclutado($usuarioB->id, $pokemon->id, 'B1');
        $member = TeamMember::create([
            'team_id' => $teamB->id,
            'pokemon_id' => $reclutadoB->id,
            'slot' => 1,
            'behavior' => 'VANGUARDIA',
        ]);

        $this->actingAs($usuarioA);

        $response = $this->postJson('/teams/update-member-role', [
            'member_id' => $member->id,
            'behavior' => 'COMBATIENTE',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseHas('team_members', ['id' => $member->id, 'behavior' => 'VANGUARDIA']);
    }

    public function test_update_member_role_via_post_equipo_en_exploracion_devuelve_422(): void
    {
        $user = $this->actingAsUser();
        $team = Team::create(['name' => 'Explorador', 'user_id' => $user->id]);
        $pokemon = $this->createPokemon(1);
        $reclutado = $this->createReclutado($user->id, $pokemon->id, 'Bulbi');
        $member = TeamMember::create([
            'team_id' => $team->id,
            'pokemon_id' => $reclutado->id,
            'slot' => 1,
            'behavior' => 'VANGUARDIA',
        ]);
        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => $province->id]);
        ExploracionActiva::create([
            'user_id' => $user->id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
            'duracion_horas' => 2,
            'inicio_exploracion' => now(),
            'llegada_destino' => now()->addHour(),
            'regreso' => null,
        ]);

        $response = $this->postJson('/teams/update-member-role', [
            'member_id' => $member->id,
            'behavior' => 'RASTREADOR',
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'No se puede modificar un equipo con exploraciones activas']);
        $this->assertDatabaseHas('team_members', ['id' => $member->id, 'behavior' => 'VANGUARDIA']);
    }

    public function test_update_member_role_equipo_en_exploracion_devuelve_422(): void
    {
        $user = $this->actingAsUser();
        $team = Team::create(['name' => 'Explorador', 'user_id' => $user->id]);
        $pokemon = $this->createPokemon(1);
        $reclutado = $this->createReclutado($user->id, $pokemon->id, 'Bulbi');
        $member = TeamMember::create([
            'team_id' => $team->id,
            'pokemon_id' => $reclutado->id,
            'slot' => 1,
            'behavior' => 'VANGUARDIA',
        ]);
        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => $province->id]);
        ExploracionActiva::create([
            'user_id' => $user->id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
            'duracion_horas' => 2,
            'inicio_exploracion' => now(),
            'llegada_destino' => now()->addHour(),
            'regreso' => null,
        ]);

        $response = $this->patchJson("/teams/member/{$member->id}/role", [
            'behavior' => 'RASTREADOR',
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'No se puede modificar un equipo con exploraciones activas']);
        $this->assertDatabaseHas('team_members', ['id' => $member->id, 'behavior' => 'VANGUARDIA']);
    }

    // ──── equipos payload con behavior y sinergia (Task C) ──────────────────

    public function test_equipos_payload_incluye_behavior_y_sinergia(): void
    {
        $user = $this->actingAsUser();
        $team = Team::create(['name' => 'Sinergia Test', 'user_id' => $user->id]);
        $this->createPokemon(1);
        $this->createPokemon(2);
        $this->createPokemon(3);
        $r1 = $this->createReclutado($user->id, 1, 'A');
        $r2 = $this->createReclutado($user->id, 2, 'B');
        $r3 = $this->createReclutado($user->id, 3, 'C');
        TeamMember::create(['team_id' => $team->id, 'pokemon_id' => $r1->id, 'slot' => 1, 'behavior' => 'VANGUARDIA']);
        TeamMember::create(['team_id' => $team->id, 'pokemon_id' => $r2->id, 'slot' => 2, 'behavior' => 'COMBATIENTE']);
        TeamMember::create(['team_id' => $team->id, 'pokemon_id' => $r3->id, 'slot' => 3, 'behavior' => 'RECOLECTOR']);

        $response = $this->get('/equipos');

        $response->assertOk();
        $teams = $response->viewData('teams');
        $this->assertCount(1, $teams);
        $teamData = $teams[0];
        $this->assertArrayHasKey('sinergia', $teamData);
        $this->assertArrayHasKey('sinergia_nombre', $teamData);
        // VANGUARDIA(V)+COMBATIENTE(C)+RECOLECTOR(R) = CRV = 'expedicion_equilibrada'
        $this->assertSame('expedicion_equilibrada', $teamData['sinergia_nombre']);
        $this->assertNotNull($teamData['sinergia']);
        $this->assertSame('expedicion_equilibrada', $teamData['sinergia']['nombre']);

        $this->assertCount(3, $teamData['members']);
        foreach ($teamData['members'] as $member) {
            $this->assertArrayHasKey('behavior', $member);
        }
        $this->assertSame('COMBATIENTE', $teamData['members'][1]['behavior']);
    }

    public function test_equipos_sin_3_miembros_tiene_sinergia_null(): void
    {
        $user = $this->actingAsUser();
        $team = Team::create(['name' => 'Incompleto', 'user_id' => $user->id]);
        $this->createPokemon(1);
        $r1 = $this->createReclutado($user->id, 1, 'A');
        TeamMember::create(['team_id' => $team->id, 'pokemon_id' => $r1->id, 'slot' => 1, 'behavior' => 'VANGUARDIA']);

        $response = $this->get('/equipos');
        $teams = $response->viewData('teams');

        $this->assertCount(1, $teams);
        $this->assertNull($teams[0]['sinergia']);
        $this->assertNull($teams[0]['sinergia_nombre']);
    }

    public function test_equipos_miembros_de_varios_equipos_con_sinergia_distinta(): void
    {
        $user = $this->actingAsUser();
        $this->createPokemon(1);
        $this->createPokemon(2);
        $this->createPokemon(3);
        $this->createPokemon(4);
        $this->createPokemon(5);
        $this->createPokemon(6);

        $team1 = Team::create(['name' => 'Alpha', 'user_id' => $user->id]);
        $team2 = Team::create(['name' => 'Beta', 'user_id' => $user->id]);

        $r1 = $this->createReclutado($user->id, 1, 'A');
        $r2 = $this->createReclutado($user->id, 2, 'B');
        $r3 = $this->createReclutado($user->id, 3, 'C');
        $r4 = $this->createReclutado($user->id, 4, 'D');
        $r5 = $this->createReclutado($user->id, 5, 'E');
        $r6 = $this->createReclutado($user->id, 6, 'F');

        TeamMember::create(['team_id' => $team1->id, 'pokemon_id' => $r1->id, 'slot' => 1, 'behavior' => 'VANGUARDIA']);
        TeamMember::create(['team_id' => $team1->id, 'pokemon_id' => $r2->id, 'slot' => 2, 'behavior' => 'COMBATIENTE']);
        TeamMember::create(['team_id' => $team1->id, 'pokemon_id' => $r3->id, 'slot' => 3, 'behavior' => 'RASTREADOR']);

        TeamMember::create(['team_id' => $team2->id, 'pokemon_id' => $r4->id, 'slot' => 1, 'behavior' => 'VANGUARDIA']);
        TeamMember::create(['team_id' => $team2->id, 'pokemon_id' => $r5->id, 'slot' => 2, 'behavior' => 'VANGUARDIA']);
        TeamMember::create(['team_id' => $team2->id, 'pokemon_id' => $r6->id, 'slot' => 3, 'behavior' => 'VANGUARDIA']);

        $response = $this->get('/equipos');
        $teams = $response->viewData('teams');

        $this->assertCount(2, $teams);
        // Alpha: V+C+T = CTV = 'caceria'
        $this->assertSame('caceria', $teams[0]['sinergia_nombre']);
        // Beta: V+V+V = VVV = 'exploracion_agresiva'
        $this->assertSame('exploracion_agresiva', $teams[1]['sinergia_nombre']);
    }
}
