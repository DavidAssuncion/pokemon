<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use App\Models\Province;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Src\Exploraciones\App\ProcesarExploracionHandler;
use Src\Shared\Bus\CommandBus;
use Tests\TestCase;

class ProcesarExploracionPoolTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::factory()->create();
        $this->actingAs($this->usuario);
    }

    /**
     * Crea una exploración activa con un pokémon en el hábitat con stats
     * (una con effort>0 y otra con effort=0) y tipos.
     */
    private function crearContextoConStats(): ExploracionActiva
    {
        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => 1, 'peligro' => 1]);

        $pokemon = Pokemon::create([
            'id' => 1,
            'name' => 'bulbasaur',
            'species_id' => 1,
            'capture_rate' => 255,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
            'hatch' => 10,
            'evolution_chain_id' => 51,
        ]);

        DB::table('pokemon_habitat')->insert([
            ['pokemon_id' => 1, 'habitat_id' => 1, 'level' => 1],
        ]);

        PokemonStat::create(['pokemon_id' => 1, 'stat' => 1, 'base_stat' => 45, 'effort' => 2]);
        PokemonStat::create(['pokemon_id' => 1, 'stat' => 2, 'base_stat' => 49, 'effort' => 0]);
        PokemonStat::create(['pokemon_id' => 1, 'stat' => 4, 'base_stat' => 65, 'effort' => 1]);

        PokemonType::create(['pokemon_id' => 1, 'type' => 12, 'slot' => 1]); // Planta
        PokemonType::create(['pokemon_id' => 1, 'type' => 4, 'slot' => 2]); // Veneno

        $team = Team::create(['name' => 'Equipo', 'user_id' => $this->usuario->id]);
        $reclutado = Reclutado::create([
            'user_id' => $this->usuario->id,
            'pokemon_id' => 1,
            'nombre' => 'Bulbi',
            'exp' => ['total' => 0],
        ]);
        TeamMember::create(['team_id' => $team->id, 'pokemon_id' => $reclutado->id, 'slot' => 1, 'behavior' => 'COMBATIENTE']);

        return ExploracionActiva::create([
            'user_id' => $this->usuario->id,
            'equipo_id' => $team->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
            'duracion_horas' => 1,
            'inicio_exploracion' => now()->subMinutes(5),
        ]);
    }

    #[Test]
    public function pool_habitat_incluye_stats_con_effort_mayor_que_cero(): void
    {
        $exploracion = $this->crearContextoConStats();

        $handler = new ProcesarExploracionHandler($this->createMock(CommandBus::class));
        $metodo = new ReflectionMethod(ProcesarExploracionHandler::class, 'poolHabitat');
        $metodo->setAccessible(true);

        /** @var list<array{id: int, capture_rate: int, hatch: int|null, tipos: list<object>, stats: list<array{stat: int, effort: int}>}> $pool */
        $pool = $metodo->invoke($handler, $exploracion);

        $this->assertCount(1, $pool);
        $this->assertSame(1, $pool[0]['id']);

        // Solo stats con effort > 0 (stat 1 con effort 2 y stat 4 con effort 1;
        // el stat 2 con effort 0 queda excluido).
        $this->assertSame(
            [['stat' => 1, 'effort' => 2], ['stat' => 4, 'effort' => 1]],
            $pool[0]['stats'],
        );

        // Los tipos del pool están presentes.
        $this->assertCount(2, $pool[0]['tipos']);
    }
}
