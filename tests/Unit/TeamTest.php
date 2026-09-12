<?php

declare(strict_types=1);

namespace Tests\Unit;

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

class TeamTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Crea un equipo con un miembro (reclutado) y devuelve [team, reclutado].
     */
    private function crearEquipoConReclutado(): array
    {
        $usuario = User::factory()->create();
        $team = Team::create(['name' => 'Alpha', 'user_id' => $usuario->id]);
        $pokemon = Pokemon::firstOrCreate(['id' => 9200], [
            'name' => 'recluta-9200',
            'species_id' => 1,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
            'evolution_chain_id' => 51,
        ]);
        $reclutado = Reclutado::create([
            'user_id' => $usuario->id,
            'pokemon_id' => $pokemon->id,
            'nombre' => 'Miembro',
            'exp' => ['total' => 0],
        ]);
        TeamMember::create(['team_id' => $team->id, 'pokemon_id' => $reclutado->id, 'slot' => 1]);

        return [$team, $reclutado];
    }

    private function crearExploracion(array $atributos = []): ExploracionActiva
    {
        $province = Province::firstOrCreate(['id' => 1], ['name' => 'Kanto']);
        $habitat = Habitat::firstOrCreate(['id' => 1], ['name' => 'Bosque', 'province_id' => 1]);
        [$team, $reclutado] = $this->crearEquipoConReclutado();

        return ExploracionActiva::create(array_merge([
            'user_id' => $team->user_id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
        ], $atributos));
    }

    public function test_team_is_not_exploring_when_no_exploraciones(): void
    {
        [$team] = $this->crearEquipoConReclutado();

        $this->assertFalse($team->isExploring());
    }

    public function test_team_is_exploring_when_has_active_exploration(): void
    {
        [$team, $reclutado] = $this->crearEquipoConReclutado();
        $this->crearExploracion(['user_id' => $team->user_id, 'reclutado_id' => $reclutado->id]);

        $this->assertTrue($team->isExploring());
    }

    public function test_team_is_not_exploring_after_exploration_completes(): void
    {
        [$team, $reclutado] = $this->crearEquipoConReclutado();
        $exploracion = $this->crearExploracion(['user_id' => $team->user_id, 'reclutado_id' => $reclutado->id]);
        $exploracion->update(['regreso' => now()]);

        $this->assertFalse($team->isExploring());
    }

    public function test_team_is_exploring_when_one_of_multiple_explorations_is_active(): void
    {
        [$team, $reclutado] = $this->crearEquipoConReclutado();

        $completed = $this->crearExploracion(['user_id' => $team->user_id, 'reclutado_id' => $reclutado->id]);
        $completed->update(['regreso' => now()]);

        $this->crearExploracion(['user_id' => $team->user_id, 'reclutado_id' => $reclutado->id, 'nivel' => 2]);

        $this->assertTrue($team->isExploring());
    }
}
