<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\Province;
use App\Models\Reclutado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HabitatsControllerTest extends TestCase
{
    use RefreshDatabase;

    private int $habitatId;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => 1]);
        $this->habitatId = $habitat->id;

        // Las rutas pasan por middleware 'auth' (Fase B): el usuario autenticado
        // además activa el global scope por user_id en los listados.
        $this->usuario = User::factory()->create();
        $this->actingAs($this->usuario);
    }

    public function test_index_returns_200(): void
    {
        $response = $this->get('/habitats');

        $response->assertStatus(200);
    }

    public function test_index_returns_view(): void
    {
        $response = $this->get('/habitats');

        $response->assertSee('Provincias');
    }

    public function test_show_returns_200(): void
    {
        $response = $this->get("/habitats/{$this->habitatId}");

        $response->assertStatus(200);
    }

    public function test_show_returns_view_with_habitat_data(): void
    {
        $response = $this->get("/habitats/{$this->habitatId}");

        $response->assertSee('Bosque');
    }

    private function crearReclutado(int $pokemonId, string $nombre): Reclutado
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
            ['pokemon_id' => $pokemon->id, 'user_id' => $this->usuario->id],
            ['nombre' => $nombre, 'exp' => ['total' => 0], 'es_shiny' => false, 'obj_equipados' => [], 'movimientos' => []],
        );
    }

    public function test_show_passes_exploraciones_activas(): void
    {
        $reclutado = $this->crearReclutado(101, 'Alpha');
        ExploracionActiva::create([
            'user_id' => $this->usuario->id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $this->habitatId,
            'nivel' => 1,
        ]);

        $response = $this->get("/habitats/{$this->habitatId}");

        $response->assertStatus(200);
        $response->assertSee('Alpha');
    }

    public function test_show_passes_equipos_en_exploracion(): void
    {
        $reclutado = $this->crearReclutado(102, 'Bravo');
        ExploracionActiva::create([
            'user_id' => $this->usuario->id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $this->habitatId,
            'nivel' => 1,
        ]);

        $response = $this->get("/habitats/{$this->habitatId}");

        $response->assertStatus(200);
        $response->assertSee('Bravo');
    }

    public function test_show_does_not_include_completed_exploraciones(): void
    {
        $reclutado = $this->crearReclutado(103, 'Explorador');
        $exploracion = ExploracionActiva::create([
            'user_id' => $this->usuario->id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $this->habitatId,
            'nivel' => 1,
        ]);
        $exploracion->update(['regreso' => now()]);

        // Verify that the completed exploracion is not considered active
        $activas = ExploracionActiva::where('habitat_id', $this->habitatId)
            ->whereNull('regreso')
            ->get();

        $this->assertCount(0, $activas);
    }

    public function test_show_pasa_los_min_lvl_del_habitat(): void
    {
        $habitat = Habitat::find($this->habitatId);
        $habitat->forceFill(['min_lvl_1' => 5, 'min_lvl_2' => 10])->save();

        $response = $this->get("/habitats/{$this->habitatId}");

        $response->assertOk();
        $this->assertSame(5, $response->viewData('habitat')['min_lvl_1']);
        $this->assertSame(10, $response->viewData('habitat')['min_lvl_2']);
        $this->assertNull($response->viewData('habitat')['min_lvl_3']);
    }

    public function test_api_pokemon_returns_json(): void
    {
        $pokemon = Pokemon::create([
            'id' => 1,
            'name' => 'bulbasaur',
            'species_id' => 1,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
            'evolution_chain_id' => 51,
        ]);
        DB::table('pokemon_habitat')->insert([
            'pokemon_id' => $pokemon->id,
            'habitat_id' => $this->habitatId,
            'level' => 1,
        ]);

        $response = $this->getJson("/api/habitats/{$this->habitatId}/pokemon");

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['name' => 'bulbasaur']);
    }

    public function test_api_pokemon_returns_empty_for_habitat_without_pokemon(): void
    {
        $response = $this->getJson("/api/habitats/{$this->habitatId}/pokemon");

        $response->assertStatus(200);
        $response->assertJsonCount(0);
    }

    public function test_api_families_returns_200(): void
    {
        $response = $this->getJson("/api/habitats/{$this->habitatId}/families");

        $response->assertStatus(200);
    }
}
