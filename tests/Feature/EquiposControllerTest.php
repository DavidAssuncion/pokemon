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

    public function test_reclutados_index_returns_200(): void
    {
        $response = $this->get('/reclutados');

        $response->assertStatus(200);
    }
}
