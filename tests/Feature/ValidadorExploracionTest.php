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

    // ── equipoDisponible (por reclutado tras RFC) ──────────────────────

    /**
     * Crea un equipo con un miembro (reclutado) para el usuario.
     */
    private function crearEquipoConReclutado(): array
    {
        $usuario = User::factory()->create();
        $team = Team::create(['name' => 'Alpha', 'user_id' => $usuario->id]);
        $pokemon = Pokemon::firstOrCreate(['id' => 9100], [
            'name' => 'recluta-9100',
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

        return ['usuario' => $usuario, 'team' => $team, 'reclutado' => $reclutado];
    }

    private function crearHabitat(): Habitat
    {
        $province = Province::firstOrCreate(['id' => 1], ['name' => 'Kanto']);

        return Habitat::firstOrCreate(['id' => 1], ['name' => 'Bosque', 'province_id' => $province->id]);
    }

    private function crearExploracion(array $atributos = []): ExploracionActiva
    {
        $habitat = $this->crearHabitat();
        $ctx = $this->crearEquipoConReclutado();

        return ExploracionActiva::create(array_merge([
            'user_id' => $ctx['usuario']->id,
            'reclutado_id' => $ctx['reclutado']->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
        ], $atributos));
    }

    public function test_team_is_available_when_no_exploraciones(): void
    {
        $ctx = $this->crearEquipoConReclutado();

        $this->assertTrue($this->validator->equipoDisponible($ctx['team']->id));
    }

    public function test_team_is_not_available_when_has_active_exploration(): void
    {
        $ctx = $this->crearEquipoConReclutado();
        $this->crearExploracion(['user_id' => $ctx['usuario']->id, 'reclutado_id' => $ctx['reclutado']->id]);

        $this->assertFalse($this->validator->equipoDisponible($ctx['team']->id));
    }

    public function test_team_is_available_after_exploration_completes(): void
    {
        $ctx = $this->crearEquipoConReclutado();
        $exploracion = $this->crearExploracion(['user_id' => $ctx['usuario']->id, 'reclutado_id' => $ctx['reclutado']->id]);
        $exploracion->update(['regreso' => now()]);

        $this->assertTrue($this->validator->equipoDisponible($ctx['team']->id));
    }

    // ── habitatTieneExploracionesActivas ───────────────────────────────

    public function test_habitat_without_exploraciones_is_not_blocked(): void
    {
        $habitat = $this->crearHabitat();

        $this->assertFalse($this->validator->habitatTieneExploracionesActivas($habitat->id));
    }

    public function test_habitat_with_active_exploration_is_blocked(): void
    {
        $habitat = $this->crearHabitat();
        $this->crearExploracion();

        $this->assertTrue($this->validator->habitatTieneExploracionesActivas($habitat->id));
    }

    public function test_habitat_is_not_blocked_after_exploration_completes(): void
    {
        $habitat = $this->crearHabitat();
        $exploracion = $this->crearExploracion();
        $exploracion->update(['regreso' => now()]);

        $this->assertFalse($this->validator->habitatTieneExploracionesActivas($habitat->id));
    }

    // ── habitatTieneExploracionesActivas: multiple teams same habitat ──

    public function test_habitat_stays_blocked_while_one_of_multiple_explorations_is_active(): void
    {
        $habitat = $this->crearHabitat();
        $ctx1 = $this->crearEquipoConReclutado();
        $ctx2 = $this->crearEquipoConReclutado();
        $exploracion1 = ExploracionActiva::create([
            'user_id' => $ctx1['usuario']->id,
            'reclutado_id' => $ctx1['reclutado']->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
        ]);
        ExploracionActiva::create([
            'user_id' => $ctx2['usuario']->id,
            'reclutado_id' => $ctx2['reclutado']->id,
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
        $ctx = $this->crearEquipoConReclutado();

        $this->assertTrue($this->validator->equipoDisponibleParaCombate($ctx['team']->id));
    }

    public function test_team_is_not_available_for_combate_when_has_active_exploration(): void
    {
        $ctx = $this->crearEquipoConReclutado();
        $this->crearExploracion(['user_id' => $ctx['usuario']->id, 'reclutado_id' => $ctx['reclutado']->id]);

        $this->assertFalse($this->validator->equipoDisponibleParaCombate($ctx['team']->id));
    }

    public function test_team_is_available_for_combate_after_exploration_completes(): void
    {
        $ctx = $this->crearEquipoConReclutado();
        $exploracion = $this->crearExploracion(['user_id' => $ctx['usuario']->id, 'reclutado_id' => $ctx['reclutado']->id]);
        $exploracion->update(['regreso' => now()]);

        $this->assertTrue($this->validator->equipoDisponibleParaCombate($ctx['team']->id));
    }

    // ── exploracionesActivas ───────────────────────────────────────────

    public function test_exploraciones_activas_returns_only_active(): void
    {
        $habitat = $this->crearHabitat();
        $this->crearExploracion();

        $completed = $this->crearExploracion(['nivel' => 2]);
        $completed->update(['regreso' => now()]);

        $activas = $this->validator->exploracionesActivas($habitat->id);

        $this->assertCount(1, $activas);
        $this->assertEquals(1, $activas[0]['nivel']);
    }

    public function test_exploraciones_activas_returns_multiple_teams(): void
    {
        $habitat = $this->crearHabitat();
        $this->crearExploracion();

        $ctx2 = $this->crearEquipoConReclutado();
        ExploracionActiva::create([
            'user_id' => $ctx2['usuario']->id,
            'reclutado_id' => $ctx2['reclutado']->id,
            'habitat_id' => $habitat->id,
            'nivel' => 3,
        ]);

        $activas = $this->validator->exploracionesActivas($habitat->id);

        $this->assertCount(2, $activas);
    }
}
