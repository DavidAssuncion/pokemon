<?php

declare(strict_types=1);

namespace Tests\Feature\Habitats;

use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\Province;
use App\Models\Reclutado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase D: niveles mínimos de hábitat (min_lvl_1/2/3) + anti-IDOR del
 * controlador de exploraciones (reclutado ajeno, recoger/cerrar ajenos).
 * Las expediciones son individuales (RF-B/RFC): el envío es por reclutado.
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
        $province = Province::firstOrCreate(['id' => 1], ['name' => 'Kanto']);

        $habitat = Habitat::firstOrCreate(['id' => 1], ['name' => 'Bosque', 'province_id' => $province->id]);

        foreach ($minLvls as $columna => $valor) {
            $habitat->forceFill([$columna => $valor])->save();
        }

        return $habitat;
    }

    private function crearReclutado(User $user, int $pokemonId = 101): Reclutado
    {
        $pokemon = Pokemon::firstOrCreate(['id' => $pokemonId], [
            'name' => 'poke-'.$pokemonId,
            'species_id' => $pokemonId,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
            'evolution_chain_id' => 51,
        ]);

        return Reclutado::firstOrCreate(
            ['pokemon_id' => $pokemon->id, 'user_id' => $user->id],
            ['nombre' => 'Reclutado', 'exp' => ['total' => 0], 'es_shiny' => false, 'obj_equipados' => [], 'movimientos' => []],
        );
    }

    // ── store: nivel mínimo del jugador ────────────────────────────────

    public function test_store_bloquea_cuando_el_nivel_del_jugador_es_inferior_al_min_lvl(): void
    {
        $user = $this->crearUsuario(1_250); // nivel 5 (10 × 5³)
        $habitat = $this->crearHabitat(['min_lvl_2' => 10]);
        $reclutado = $this->crearReclutado($user);

        $this->actingAs($user);

        $response = $this->post('/exploraciones', [
            'reclutado_id' => $reclutado->id,
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
        $reclutado = $this->crearReclutado($user);

        $this->actingAs($user);

        $response = $this->postJson('/exploraciones', [
            'reclutado_id' => $reclutado->id,
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
        $reclutado = $this->crearReclutado($user);

        $this->actingAs($user);

        $response = $this->post('/exploraciones', [
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
            'level' => 2,
        ]);

        $response->assertSessionHas('success', 'Exploración iniciada correctamente.');
        $this->assertDatabaseHas('exploraciones_activas', [
            'user_id' => $user->id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
            'nivel' => 2,
        ]);
    }

    public function test_store_permite_cuando_el_min_lvl_es_null(): void
    {
        $user = $this->crearUsuario(1_250); // nivel 5
        $habitat = $this->crearHabitat(); // sin restricciones (null)
        $reclutado = $this->crearReclutado($user);

        $this->actingAs($user);

        $response = $this->post('/exploraciones', [
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
            'level' => 1,
        ]);

        $response->assertSessionHas('success', 'Exploración iniciada correctamente.');
        $this->assertDatabaseHas('exploraciones_activas', [
            'user_id' => $user->id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
        ]);
    }

    // ── store: propiedad del reclutado (anti-IDOR) ─────────────────────

    public function test_store_rechaza_un_reclutado_de_otro_usuario(): void
    {
        $usuarioA = $this->crearUsuario(1_250);
        $usuarioB = $this->crearUsuario(1_250);
        $habitat = $this->crearHabitat();
        $reclutadoB = $this->crearReclutado($usuarioB);

        $this->actingAs($usuarioA);

        $response = $this->post('/exploraciones', [
            'reclutado_id' => $reclutadoB->id,
            'habitat_id' => $habitat->id,
            'level' => 1,
        ]);

        $response->assertSessionHasErrors('reclutado_id');
        $this->assertDatabaseCount('exploraciones_activas', 0);
    }

    // ── recoger / cerrar: exploración ajena → 404 (global scope) ───────

    public function test_recoger_una_exploracion_de_otro_usuario_devuelve_404(): void
    {
        $usuarioA = $this->crearUsuario(1_250);
        $usuarioB = $this->crearUsuario(1_250);
        $habitat = $this->crearHabitat();
        $reclutadoB = $this->crearReclutado($usuarioB);
        $exploracion = ExploracionActiva::create([
            'user_id' => $usuarioB->id,
            'reclutado_id' => $reclutadoB->id,
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
        $reclutadoB = $this->crearReclutado($usuarioB);
        $exploracion = ExploracionActiva::create([
            'user_id' => $usuarioB->id,
            'reclutado_id' => $reclutadoB->id,
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
        $reclutado = $this->crearReclutado($user);

        ExploracionActiva::create([
            'user_id' => $user->id,
            'reclutado_id' => $reclutado->id,
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
        $reclutado = $this->crearReclutado($user);

        ExploracionActiva::create([
            'user_id' => $user->id,
            'reclutado_id' => $reclutado->id,
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
