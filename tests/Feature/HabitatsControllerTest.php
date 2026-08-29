<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HabitatsControllerTest extends TestCase
{
    use RefreshDatabase;

    private int $habitatId;

    protected function setUp(): void
    {
        parent::setUp();

        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => 1]);
        $this->habitatId = $habitat->id;
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

    public function test_show_passes_exploraciones_activas(): void
    {
        $team = \App\Models\Team::create(['name' => 'Alpha', 'user_id' => User::factory()->create()->id]);
        \App\Models\ExploracionActiva::create([
            'user_id' => $team->user_id,
            'equipo_id' => $team->id,
            'habitat_id' => $this->habitatId,
            'nivel' => 1,
        ]);

        $response = $this->get("/habitats/{$this->habitatId}");

        $response->assertStatus(200);
        $response->assertSee('Alpha');
    }

    public function test_show_passes_equipos_en_exploracion(): void
    {
        $team = \App\Models\Team::create(['name' => 'Bravo', 'user_id' => User::factory()->create()->id]);
        \App\Models\ExploracionActiva::create([
            'user_id' => $team->user_id,
            'equipo_id' => $team->id,
            'habitat_id' => $this->habitatId,
            'nivel' => 1,
        ]);

        $response = $this->get("/habitats/{$this->habitatId}");

        $response->assertStatus(200);
        $response->assertSee('Bravo');
    }

    public function test_show_does_not_include_completed_exploraciones(): void
    {
        $team = \App\Models\Team::create(['name' => 'Explorador', 'user_id' => User::factory()->create()->id]);
        $exploracion = \App\Models\ExploracionActiva::create([
            'user_id' => $team->user_id,
            'equipo_id' => $team->id,
            'habitat_id' => $this->habitatId,
            'nivel' => 1,
        ]);
        $exploracion->update(['regreso' => now()]);

        // Verify that the completed exploracion is not considered active
        $activas = \App\Models\ExploracionActiva::where('habitat_id', $this->habitatId)
            ->whereNull('regreso')
            ->get();

        $this->assertCount(0, $activas);
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
