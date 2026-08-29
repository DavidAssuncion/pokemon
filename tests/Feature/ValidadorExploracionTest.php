<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\Province;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Habitats\App\ValidadorExploracion;
use Tests\TestCase;

class ValidadorExploracionTest extends TestCase
{
    use RefreshDatabase;

    private ValidadorExploracion $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ValidadorExploracion();
    }

    // ── equipoDisponible ───────────────────────────────────────────────

    // ── cumpleNivelMinimo ──────────────────────────────────────────────

    public function test_cumple_nivel_minimo_sin_restriccion_null(): void
    {
        $this->assertTrue($this->validator->cumpleNivelMinimo(1, null));
    }

    public function test_cumple_nivel_minimo_cuando_el_nivel_es_superior(): void
    {
        $this->assertTrue($this->validator->cumpleNivelMinimo(12, 10));
    }

    public function test_cumple_nivel_minimo_en_el_limite_exacto(): void
    {
        $this->assertTrue($this->validator->cumpleNivelMinimo(10, 10));
    }

    public function test_no_cumple_nivel_minimo_cuando_el_nivel_es_inferior(): void
    {
        $this->assertFalse($this->validator->cumpleNivelMinimo(9, 10));
    }

    // ── equipoDisponible ───────────────────────────────────────────────

    public function test_team_is_available_when_no_exploraciones(): void
    {
        $team = Team::create(['name' => 'Alpha', 'user_id' => User::factory()->create()->id]);

        $this->assertTrue($this->validator->equipoDisponible($team->id));
    }

    public function test_team_is_not_available_when_has_active_exploration(): void
    {
        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => 1]);
        $team = Team::create(['name' => 'Alpha', 'user_id' => User::factory()->create()->id]);

        ExploracionActiva::create([
            'user_id' => $team->user_id,
            'equipo_id' => $team->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
        ]);

        $this->assertFalse($this->validator->equipoDisponible($team->id));
    }

    public function test_team_is_available_after_exploration_completes(): void
    {
        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => 1]);
        $team = Team::create(['name' => 'Alpha', 'user_id' => User::factory()->create()->id]);

        $exploracion = ExploracionActiva::create([
            'user_id' => $team->user_id,
            'equipo_id' => $team->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
        ]);
        // Simulate completion
        $exploracion->update(['regreso' => now()]);

        $this->assertTrue($this->validator->equipoDisponible($team->id));
    }

    // ── habitatTieneExploracionesActivas ───────────────────────────────

    public function test_habitat_without_exploraciones_is_not_blocked(): void
    {
        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => 1]);

        $this->assertFalse($this->validator->habitatTieneExploracionesActivas($habitat->id));
    }

    public function test_habitat_with_active_exploration_is_blocked(): void
    {
        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => 1]);
        $team = Team::create(['name' => 'Alpha', 'user_id' => User::factory()->create()->id]);

        ExploracionActiva::create([
            'user_id' => $team->user_id,
            'equipo_id' => $team->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
        ]);

        $this->assertTrue($this->validator->habitatTieneExploracionesActivas($habitat->id));
    }

    public function test_habitat_is_not_blocked_after_exploration_completes(): void
    {
        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => 1]);
        $team = Team::create(['name' => 'Alpha', 'user_id' => User::factory()->create()->id]);

        $exploracion = ExploracionActiva::create([
            'user_id' => $team->user_id,
            'equipo_id' => $team->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
        ]);
        $exploracion->update(['regreso' => now()]);

        $this->assertFalse($this->validator->habitatTieneExploracionesActivas($habitat->id));
    }

    // ── habitatTieneExploracionesActivas: multiple teams same habitat ──

    public function test_habitat_stays_blocked_while_one_of_multiple_explorations_is_active(): void
    {
        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => 1]);
        $team1 = Team::create(['name' => 'Alpha', 'user_id' => User::factory()->create()->id]);
        $team2 = Team::create(['name' => 'Bravo', 'user_id' => User::factory()->create()->id]);

        $exploracion1 = ExploracionActiva::create([
            'user_id' => $team1->user_id,
            'equipo_id' => $team1->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
        ]);
        ExploracionActiva::create([
            'user_id' => $team2->user_id,
            'equipo_id' => $team2->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
        ]);

        // Both active → habitat blocked
        $this->assertTrue($this->validator->habitatTieneExploracionesActivas($habitat->id));

        // Team1 completes, but team2 still active → still blocked
        $exploracion1->update(['regreso' => now()]);
        $this->assertTrue($this->validator->habitatTieneExploracionesActivas($habitat->id));
    }

    // ── equipoDisponibleParaCombate ─────────────────────────────────────

    public function test_team_is_available_for_combate_when_no_exploraciones(): void
    {
        $team = Team::create(['name' => 'Alpha', 'user_id' => User::factory()->create()->id]);

        $this->assertTrue($this->validator->equipoDisponibleParaCombate($team->id));
    }

    public function test_team_is_not_available_for_combate_when_has_active_exploration(): void
    {
        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => 1]);
        $team = Team::create(['name' => 'Alpha', 'user_id' => User::factory()->create()->id]);

        ExploracionActiva::create([
            'user_id' => $team->user_id,
            'equipo_id' => $team->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
        ]);

        $this->assertFalse($this->validator->equipoDisponibleParaCombate($team->id));
    }

    public function test_team_is_available_for_combate_after_exploration_completes(): void
    {
        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => 1]);
        $team = Team::create(['name' => 'Alpha', 'user_id' => User::factory()->create()->id]);

        $exploracion = ExploracionActiva::create([
            'user_id' => $team->user_id,
            'equipo_id' => $team->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
        ]);
        $exploracion->update(['regreso' => now()]);

        $this->assertTrue($this->validator->equipoDisponibleParaCombate($team->id));
    }

    // ── exploracionesActivas ───────────────────────────────────────────

    public function test_exploraciones_activas_returns_only_active(): void
    {
        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => 1]);
        $team = Team::create(['name' => 'Alpha', 'user_id' => User::factory()->create()->id]);

        ExploracionActiva::create([
            'user_id' => $team->user_id,
            'equipo_id' => $team->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
        ]);

        $completed = ExploracionActiva::create([
            'user_id' => $team->user_id,
            'equipo_id' => $team->id,
            'habitat_id' => $habitat->id,
            'nivel' => 2,
        ]);
        $completed->update(['regreso' => now()]);

        $activas = $this->validator->exploracionesActivas($habitat->id);

        $this->assertCount(1, $activas);
        $this->assertEquals(1, $activas[0]['nivel']);
    }

    public function test_exploraciones_activas_returns_multiple_teams(): void
    {
        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => 1]);
        $team1 = Team::create(['name' => 'Alpha', 'user_id' => User::factory()->create()->id]);
        $team2 = Team::create(['name' => 'Bravo', 'user_id' => User::factory()->create()->id]);

        ExploracionActiva::create([
            'user_id' => $team1->user_id,
            'equipo_id' => $team1->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
        ]);
        ExploracionActiva::create([
            'user_id' => $team2->user_id,
            'equipo_id' => $team2->id,
            'habitat_id' => $habitat->id,
            'nivel' => 3,
        ]);

        $activas = $this->validator->exploracionesActivas($habitat->id);

        $this->assertCount(2, $activas);
    }
}
