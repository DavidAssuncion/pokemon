<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Pokemon;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquiposControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_team(): void
    {
        $response = $this->post('/teams', ['name' => 'Mewtwo Fighters']);

        $response->assertRedirect();
        $this->assertDatabaseHas('teams', ['name' => 'Mewtwo Fighters']);
    }

    public function test_store_redirects_back(): void
    {
        $response = $this->post('/teams', ['name' => 'Team Rocket']);

        $response->assertRedirect();
    }

    public function test_store_validates_name_is_required(): void
    {
        $response = $this->post('/teams', []);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('teams', 0);
    }

    public function test_destroy_removes_team(): void
    {
        $team = Team::create(['name' => 'Disposable']);

        $response = $this->delete("/teams/{$team->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
    }

    public function test_add_member_creates_team_member(): void
    {
        $team = Team::create(['name' => 'Alpha']);
        $pokemon = Pokemon::create([
            'id' => 1,
            'name' => 'bulbasaur',
            'species_id' => 1,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);
        $reclutado = Reclutado::create([
            'nombre' => 'Bulbi',
            'pokemon_id' => $pokemon->id,
            'exp' => ['exp' => 100],
            'obj_equipados' => [],
            'movimientos' => [],
        ]);

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
        $response = $this->post('/teams/add-member', []);

        $response->assertSessionHasErrors(['team_id', 'reclutado_id', 'slot', 'behavior']);
    }

    public function test_remove_member_deletes_team_member(): void
    {
        $team = Team::create(['name' => 'Alpha']);
        $pokemon = Pokemon::create([
            'id' => 1,
            'name' => 'bulbasaur',
            'species_id' => 1,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);
        $reclutado = Reclutado::create([
            'nombre' => 'Bulbi',
            'pokemon_id' => $pokemon->id,
            'exp' => ['exp' => 100],
            'obj_equipados' => [],
            'movimientos' => [],
        ]);
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
        $team = Team::create(['name' => 'Alpha']);
        $pokemon = Pokemon::create([
            'id' => 1,
            'name' => 'bulbasaur',
            'species_id' => 1,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);
        $reclutado = Reclutado::create([
            'nombre' => 'Bulbi',
            'pokemon_id' => $pokemon->id,
            'exp' => ['exp' => 100],
            'obj_equipados' => [],
            'movimientos' => [],
        ]);

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
        $team = Team::create(['name' => 'Alpha']);
        $pokemon1 = Pokemon::create([
            'id' => 1,
            'name' => 'bulbasaur',
            'species_id' => 1,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);
        $pokemon2 = Pokemon::create([
            'id' => 2,
            'name' => 'charmander',
            'species_id' => 4,
            'capture_rate' => 45,
            'base_experience' => 62,
            'height' => 6,
            'weight' => 85,
        ]);
        $reclutado1 = Reclutado::create([
            'nombre' => 'Bulbi',
            'pokemon_id' => $pokemon1->id,
            'exp' => ['exp' => 100],
            'obj_equipados' => [],
            'movimientos' => [],
        ]);
        $reclutado2 = Reclutado::create([
            'nombre' => 'Char',
            'pokemon_id' => $pokemon2->id,
            'exp' => ['exp' => 100],
            'obj_equipados' => [],
            'movimientos' => [],
        ]);
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
        $team1 = Team::create(['name' => 'Alpha']);
        $team2 = Team::create(['name' => 'Beta']);
        $pokemon = Pokemon::create([
            'id' => 1,
            'name' => 'bulbasaur',
            'species_id' => 1,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);
        $reclutado = Reclutado::create([
            'nombre' => 'Bulbi',
            'pokemon_id' => $pokemon->id,
            'exp' => ['exp' => 100],
            'obj_equipados' => [],
            'movimientos' => [],
        ]);
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
            'behavior' => 'SOPORTE',
        ]);

        $response->assertStatus(422)->assertJson(['error' => 'Pokémon ya está en un equipo']);
    }

    public function test_destroy_json_rejects_team_with_active_exploration(): void
    {
        $team = Team::create(['name' => 'Exploring']);
        $province = \App\Models\Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = \App\Models\Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => 1]);
        \App\Models\ExploracionActiva::create([
            'equipo_id' => $team->id,
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
        $team = Team::create(['name' => 'Alpha']);
        $pokemon = Pokemon::create([
            'id' => 1,
            'name' => 'bulbasaur',
            'species_id' => 1,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);
        $reclutado = Reclutado::create([
            'nombre' => 'Bulbi',
            'pokemon_id' => $pokemon->id,
            'exp' => ['exp' => 100],
            'obj_equipados' => [],
            'movimientos' => [],
        ]);
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
        $response = $this->get('/reclutados');

        $response->assertRedirect('/equipos');
    }
}
