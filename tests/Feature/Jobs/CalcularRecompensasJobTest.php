<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\CalcularRecompensasJob;
use App\Models\Caramelo;
use App\Models\EvolutionChain;
use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\Province;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Habitats\App\ValidadorExploracion;
use Tests\TestCase;

class CalcularRecompensasJobTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private ExploracionActiva $exploracion;

    protected function setUp(): void
    {
        parent::setUp();

        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => 1]);

        // Create evolution chain and pokemon
        $chain = EvolutionChain::create(['data' => '{"stages": 3}']);

        $pokemon1 = Pokemon::create([
            'id' => 1,
            'name' => 'bulbasaur',
            'species_id' => 1,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
            'evolution_chain_id' => $chain->id,
        ]);

        $pokemon2 = Pokemon::create([
            'id' => 2,
            'name' => 'charmander',
            'species_id' => 4,
            'capture_rate' => 45,
            'base_experience' => 62,
            'height' => 6,
            'weight' => 85,
            'evolution_chain_id' => $chain->id,
        ]);

        // Create team and members
        $this->team = Team::create(['name' => 'Equipo Test']);

        $reclutado1 = Reclutado::create([
            'pokemon_id' => $pokemon1->id,
            'nombre' => 'Bulbi',
            'exp' => ['total' => 100],
        ]);
        $reclutado2 = Reclutado::create([
            'pokemon_id' => $pokemon2->id,
            'nombre' => 'Char',
            'exp' => ['total' => 50],
        ]);

        TeamMember::create(['team_id' => $this->team->id, 'pokemon_id' => $reclutado1->id, 'slot' => 1, 'behavior' => 'COMBATIENTE']);
        TeamMember::create(['team_id' => $this->team->id, 'pokemon_id' => $reclutado2->id, 'slot' => 2, 'behavior' => 'COMBATIENTE']);

        // Create exploracion with defeated pokemon
        $this->exploracion = ExploracionActiva::create([
            'equipo_id' => $this->team->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
            'eventos' => ['derrotados' => [1, 2]],
        ]);
    }

    public function test_calculates_candy_rewards_based_on_evolution_phase(): void
    {
        CalcularRecompensasJob::dispatch($this->exploracion->id);

        $caramelo = Caramelo::first();
        $this->assertNotNull($caramelo);
        // Both pokemon are in same chain, species_id 1 and 4
        // Phase for pokemon1 (species_id=1): count of pokemon in chain with species_id <= 1
        // Phase for pokemon2 (species_id=4): count of pokemon in chain with species_id <= 4
        // Both are in chain with 2 pokemon, species_ids 1 and 4
        // Phase 1 = 1 (only bulbasaur), Phase 2 = 2 (both bulbasaur and charmander)
        $this->assertEquals(3, $caramelo->cantidad); // 1 + 2 = 3
    }

    public function test_distributes_experience_equally(): void
    {
        CalcularRecompensasJob::dispatch($this->exploracion->id);

        $reclutado1 = Reclutado::where('pokemon_id', 1)->first();
        $reclutado2 = Reclutado::where('pokemon_id', 2)->first();

        // 2 defeated * 10 exp = 20 total, / 2 members = 10 each
        $this->assertEquals(110, $reclutado1->exp['total']); // 100 + 10
        $this->assertEquals(60, $reclutado2->exp['total']);  // 50 + 10
    }

    public function test_does_nothing_without_eventos(): void
    {
        $this->exploracion->update(['eventos' => null]);

        CalcularRecompensasJob::dispatch($this->exploracion->id);

        $this->assertDatabaseCount('caramelos', 0);
    }

    public function test_handles_empty_derrotados(): void
    {
        $this->exploracion->update(['eventos' => ['derrotados' => []]]);

        CalcularRecompensasJob::dispatch($this->exploracion->id);

        $this->assertDatabaseCount('caramelos', 0);
    }

    public function test_marks_exploration_as_complete_with_regreso(): void
    {
        CalcularRecompensasJob::dispatch($this->exploracion->id);

        $this->assertDatabaseHas('exploraciones_activas', [
            'id' => $this->exploracion->id,
        ]);

        $this->exploracion->refresh();
        $this->assertNotNull($this->exploracion->regreso);

        // Verify the team is now available for new explorations
        $validator = new ValidadorExploracion();
        $this->assertTrue($validator->equipoDisponible($this->team->id));
    }

    public function test_exploration_without_eventos_stays_active(): void
    {
        $this->exploracion->update(['eventos' => null]);

        CalcularRecompensasJob::dispatch($this->exploracion->id);

        // Early return → no regreso set
        $this->exploracion->refresh();
        $this->assertNull($this->exploracion->regreso);
    }
}
