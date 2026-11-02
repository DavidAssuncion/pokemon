<?php

declare(strict_types=1);

namespace Tests\Feature\Gimnasios;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Livewire\Presenters\PresentadorResultadoBatalla;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Support\BattleSessionService;
use Database\Seeders\GymSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Collections\DatosPokemonBatallaCollection;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\Posicion;
use Src\Shared\Tipos\TipoPokemon;
use Tests\TestCase;

/**
 * Verifica que el presentador traduce el gimnasio y la etapa a su perfil
 * IV/EV (índice 1-18 + líder) antes de otorgar recompensas.
 */
class PresentadorGimnasioDificultadTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GymSeeder::class);
        $this->user = User::factory()->create(['experiencia' => 10 * 20 ** 3]);
        $this->actingAs($this->user);
        $this->team = $this->crearEquipo();
    }

    #[Test]
    public function el_lider_del_primer_gimnasio_usa_el_perfil_top_dos(): void
    {
        $this->crearPokemonRival(301);

        [$battle, $battleId] = $this->crearBatalla([
            'tipo' => 'gimnasio',
            'gym_id' => 'bug',
            'stage' => 4,
            'nivel_rival' => 20,
            'user_id' => (int) $this->user->id,
            'team_id' => (int) $this->team->id,
        ]);

        $resultado = PresentadorResultadoBatalla::procesar($battle, $battleId, app(BattleSessionService::class));
        $rewards = $resultado['rewards'];

        $this->assertSame('Medalla Bicho', $rewards['medalla'] ?? null);
        $this->assertSame(33, $rewards['caramelos'][0]['cantidad']);
        $this->assertSame(18, $rewards['caramelos'][1]['cantidad']);
        // expDerrota = intdiv(6400 * 20, 5) = 25.600; caramelo tipo TopDos (1.90):
        // floor(25.600 * 10 * 1.90 / 500) = 972.
        $this->assertSame(972, $rewards['caramelos'][2]['cantidad']);
    }

    #[Test]
    public function el_ultimo_gimnasio_usa_el_perfil_completo(): void
    {
        $this->crearPokemonRival(301);

        [$battle, $battleId] = $this->crearBatalla([
            'tipo' => 'gimnasio',
            'gym_id' => 'dragon',
            'stage' => 1,
            'nivel_rival' => 20,
            'user_id' => (int) $this->user->id,
            'team_id' => (int) $this->team->id,
        ]);

        $resultado = PresentadorResultadoBatalla::procesar($battle, $battleId, app(BattleSessionService::class));
        $rewards = $resultado['rewards'];

        $this->assertArrayNotHasKey('medalla', $rewards);
        $this->assertSame(33, $rewards['caramelos'][0]['cantidad']);
        $this->assertSame(28, $rewards['caramelos'][1]['cantidad']);
        // expDerrota = intdiv(6400 * 20, 5) = 25.600; caramelo tipo Completo (2.40):
        // floor(25.600 * 10 * 2.40 / 500) = 1.228.
        $this->assertSame(1228, $rewards['caramelos'][2]['cantidad']);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{0: AgregadoBatalla, 1: string}
     */
    private function crearBatalla(array $meta): array
    {
        $pokemonJugador = $this->crearPokemonBatalla(201);
        $pokemonRival = $this->crearPokemonBatalla(301);

        $session = app(BattleSessionService::class);

        $battleId = 'battle_gimnasio_test_'.uniqid();
        $team1 = EquipoBatalla::fromData(new DatosPokemonBatallaCollection([$pokemonJugador]), 'Jugador');
        $team2 = EquipoBatalla::fromData(new DatosPokemonBatallaCollection([$pokemonRival]), 'Gimnasio');

        $battle = new AgregadoBatalla($team1, $team2);
        $battle->triggerBattleStartEffects();

        $session->guardar($battleId, $battle);
        $session->guardarMeta($battleId, $meta);

        return [$battle, $battleId];
    }

    private function crearPokemonBatalla(int $id): DatosPokemonBatalla
    {
        return new DatosPokemonBatalla(
            id: 'batalla_'.$id,
            nombre: 'pokemon-'.$id,
            hp: 80,
            atk: 80,
            def: 80,
            spAtk: 80,
            spDef: 80,
            speed: 80,
            tipos: [TipoPokemon::NORMAL],
            posicion: Posicion::VANGUARDIA,
            moves: [new MovimientoBatalla(
                nombre: 'Placaje',
                potencia: 50,
                tipo: TipoPokemon::NORMAL,
                categoria: CategoriaMovimiento::FISICO,
            )],
            speciesId: $id,
        );
    }

    private function crearPokemonRival(int $id): Pokemon
    {
        $pokemon = Pokemon::create([
            'id' => $id,
            'name' => 'rival-'.$id,
            'species_id' => $id,
            'capture_rate' => 45,
            'base_experience' => 6400,
            'height' => 7,
            'weight' => 69,
            'evolution_chain_id' => 700,
        ]);

        PokemonStat::create([
            'pokemon_id' => $pokemon->id,
            'stat' => StatEnum::HP->value,
            'base_stat' => 80,
            'effort' => 1,
        ]);

        PokemonType::create([
            'pokemon_id' => $pokemon->id,
            'type' => TipoEnum::NORMAL,
            'slot' => 1,
        ]);

        return $pokemon;
    }

    private function crearEquipo(): Team
    {
        $team = Team::create(['name' => 'Equipo Test', 'user_id' => $this->user->id]);

        $pokemon = Pokemon::create([
            'id' => 9501,
            'name' => 'jugador-1',
            'species_id' => 9501,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);

        foreach (StatEnum::cases() as $stat) {
            PokemonStat::create([
                'pokemon_id' => $pokemon->id,
                'stat' => $stat->value,
                'base_stat' => 100,
                'effort' => 0,
            ]);
        }

        PokemonType::create([
            'pokemon_id' => $pokemon->id,
            'type' => TipoEnum::NORMAL,
            'slot' => 1,
        ]);

        $reclutado = Reclutado::create([
            'user_id' => $this->user->id,
            'nombre' => 'Jugador',
            'pokemon_id' => $pokemon->id,
            'exp' => ['exp' => 100],
            'obj_equipados' => [],
            'movimientos' => [],
        ]);

        TeamMember::create([
            'team_id' => $team->id,
            'pokemon_id' => $reclutado->id,
            'slot' => 1,
            'behavior' => 'VANGUARDIA',
        ]);

        return $team;
    }
}
