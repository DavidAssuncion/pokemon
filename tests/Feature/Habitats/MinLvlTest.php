<?php

declare(strict_types=1);

namespace Tests\Feature\Habitats;

use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\Province;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase D: niveles mínimos de hábitat (min_lvl_1/2/3) + anti-IDOR del
 * controlador de exploraciones (equipo ajeno, recoger/cerrar ajenos).
 */
class MinLvlTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuario(int $experiencia): User
    {
        return User::factory()->create(['experiencia' => $experiencia]);
    }

    /**
     * @param  array<string, int|null>  $minLvls
     */
    private function crearHabitat(array $minLvls = []): Habitat
    {
        Province::create(['id' => 1, 'name' => 'Kanto']);

        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => 1]);

        foreach ($minLvls as $columna => $valor) {
            $habitat->forceFill([$columna => $valor])->save();
        }

        return $habitat;
    }

    private function crearEquipo(User $user): Team
    {
        return Team::create(['name' => 'Alpha', 'user_id' => $user->id]);
    }

    // ── store: nivel mínimo del jugador ────────────────────────────────

    public function test_store_bloquea_cuando_el_nivel_del_jugador_es_inferior_al_min_lvl(): void
    {
        $user = $this->crearUsuario(1_250); // nivel 5 (10 × 5³)
        $habitat = $this->crearHabitat(['min_lvl_2' => 10]);
        $team = $this->crearEquipo($user);

        $this->actingAs($user);

        $response = $this->post('/exploraciones', [
            'team_id' => $team->id,
            'habitat_id' => $habitat->id,
            'level' => 2,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Requiere nivel Nv 10 para explorar esta zona.');
        $this->assertDatabaseCount('exploraciones_activas', 0);
    }

    public function test_store_bloquea_min_lvl_con_422_json(): void
    {
        $user = $this->crearUsuario(1_250); // nivel 5
        $habitat = $this->crearHabitat(['min_lvl_2' => 10]);
        $team = $this->crearEquipo($user);

        $this->actingAs($user);

        $response = $this->postJson('/exploraciones', [
            'team_id' => $team->id,
            'habitat_id' => $habitat->id,
            'level' => 2,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Requiere nivel Nv 10 para explorar esta zona.']);
        $this->assertDatabaseCount('exploraciones_activas', 0);
    }

    public function test_store_permite_cuando_el_nivel_del_jugador_es_igual_al_min_lvl(): void
    {
        $user = $this->crearUsuario(10_000); // nivel 10 (10 × 10³)
        $habitat = $this->crearHabitat(['min_lvl_2' => 10]);
        $team = $this->crearEquipo($user);

        $this->actingAs($user);

        $response = $this->post('/exploraciones', [
            'team_id' => $team->id,
            'habitat_id' => $habitat->id,
            'level' => 2,
        ]);

        $response->assertSessionHas('success', 'Exploración iniciada correctamente.');
        $this->assertDatabaseHas('exploraciones_activas', [
            'user_id' => $user->id,
            'equipo_id' => $team->id,
            'habitat_id' => $habitat->id,
            'nivel' => 2,
        ]);
    }

    public function test_store_permite_cuando_el_min_lvl_es_null(): void
    {
        $user = $this->crearUsuario(1_250); // nivel 5
        $habitat = $this->crearHabitat(); // sin restricciones (null)
        $team = $this->crearEquipo($user);

        $this->actingAs($user);

        $response = $this->post('/exploraciones', [
            'team_id' => $team->id,
            'habitat_id' => $habitat->id,
            'level' => 1,
        ]);

        $response->assertSessionHas('success', 'Exploración iniciada correctamente.');
        $this->assertDatabaseHas('exploraciones_activas', [
            'user_id' => $user->id,
            'equipo_id' => $team->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
        ]);
    }

    // ── store: propiedad del equipo (anti-IDOR) ────────────────────────

    public function test_store_rechaza_un_equipo_de_otro_usuario(): void
    {
        $usuarioA = $this->crearUsuario(1_250);
        $usuarioB = $this->crearUsuario(1_250);
        $habitat = $this->crearHabitat();
        $teamB = $this->crearEquipo($usuarioB);

        $this->actingAs($usuarioA);

        $response = $this->post('/exploraciones', [
            'team_id' => $teamB->id,
            'habitat_id' => $habitat->id,
            'level' => 1,
        ]);

        $response->assertSessionHasErrors('team_id');
        $this->assertDatabaseCount('exploraciones_activas', 0);
    }

    // ── recoger / cerrar: exploración ajena → 404 (global scope) ───────

    public function test_recoger_una_exploracion_de_otro_usuario_devuelve_404(): void
    {
        $usuarioA = $this->crearUsuario(1_250);
        $usuarioB = $this->crearUsuario(1_250);
        $habitat = $this->crearHabitat();
        $teamB = $this->crearEquipo($usuarioB);
        $exploracion = ExploracionActiva::create([
            'user_id' => $usuarioB->id,
            'equipo_id' => $teamB->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
        ]);

        $this->actingAs($usuarioA);

        $this->post("/exploraciones/{$exploracion->id}/recoger")
            ->assertNotFound();
    }

    public function test_cerrar_una_exploracion_de_otro_usuario_devuelve_404(): void
    {
        $usuarioA = $this->crearUsuario(1_250);
        $usuarioB = $this->crearUsuario(1_250);
        $habitat = $this->crearHabitat();
        $teamB = $this->crearEquipo($usuarioB);
        $exploracion = ExploracionActiva::create([
            'user_id' => $usuarioB->id,
            'equipo_id' => $teamB->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
            'regreso' => now(),
        ]);

        $this->actingAs($usuarioA);

        $this->post("/exploraciones/{$exploracion->id}/cerrar")
            ->assertNotFound();
    }

    // ── index: min_lvl del hábitat para el badge ───────────────────────

    public function test_index_incluye_el_min_lvl_del_habitat_para_el_nivel_de_la_exploracion(): void
    {
        $user = $this->crearUsuario(10_000);
        $habitat = $this->crearHabitat(['min_lvl_2' => 10]);
        $team = $this->crearEquipo($user);

        ExploracionActiva::create([
            'user_id' => $user->id,
            'equipo_id' => $team->id,
            'habitat_id' => $habitat->id,
            'nivel' => 2,
        ]);

        $this->actingAs($user);

        $response = $this->get('/exploraciones');

        $response->assertOk();
        $activas = $response->viewData('activas');
        $this->assertSame(10, $activas[0]['min_lvl']);
    }

    public function test_index_min_lvl_null_cuando_el_habitat_no_tiene_restriccion(): void
    {
        $user = $this->crearUsuario(10_000);
        $habitat = $this->crearHabitat(); // sin restricciones
        $team = $this->crearEquipo($user);

        ExploracionActiva::create([
            'user_id' => $user->id,
            'equipo_id' => $team->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
        ]);

        $this->actingAs($user);

        $response = $this->get('/exploraciones');

        $response->assertOk();
        $activas = $response->viewData('activas');
        $this->assertNull($activas[0]['min_lvl']);
    }
}
