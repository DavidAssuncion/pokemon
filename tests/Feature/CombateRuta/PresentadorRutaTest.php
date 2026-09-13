<?php

declare(strict_types=1);

namespace Tests\Feature\CombateRuta;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\EquipoBatalla;
use Tests\TestCase;

class PresentadorRutaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['experiencia' => 10 * 20 ** 3]);
        $this->team = $this->crearEquipo();
    }

    #[Test]
    public function victoria_devuelve_recompensas_y_habitat_id(): void
    {
        $pokemonRival = $this->crearPokemonRival(101);

        [$battle, $battleId] = $this->crearBatalla(['tipo' => 'ruta', 'habitat_id' => 10, 'nivel' => 1, 'user_id' => (int) $this->user->id, 'team_id' => (int) $this->team->id]);

        $resultado = PresentadorResultadoBatalla::procesar($battle, $battleId, app(BattleSessionService::class));

        $this->assertSame(10, $resultado['habitatId']);
        $this->assertArrayHasKey('rewards', $resultado);
        $this->assertSame([], $resultado['log']);
    }

    #[Test]
    public function derrota_no_devuelve_recompensas(): void
    {
        [$battle, $battleId] = $this->crearBatalla(['tipo' => 'ruta', 'habitat_id' => 11, 'nivel' => 1, 'user_id' => (int) $this->user->id, 'team_id' => (int) $this->team->id]);

        foreach ($battle->team1->combatientesCollection()->all() as $combatiente) {
            $combatiente->setHpActual(0);
        }

        $resultado = PresentadorResultadoBatalla::procesar($battle, $battleId, app(BattleSessionService::class));

        $this->assertSame(11, $resultado['habitatId']);
        $this->assertSame([], $resultado['rewards']);
    }

    private function crearBatalla(array $meta): array
    {
        $pokemonJugador = $this->crearPokemonBatalla(201);
        $pokemonRival = $this->crearPokemonBatalla(301);

        $session = app(BattleSessionService::class);

        $battleId = 'battle_ruta_test_'.uniqid();
        $team1 = EquipoBatalla::fromData([$pokemonJugador], 'Jugador');
        $team2 = EquipoBatalla::fromData([$pokemonRival], 'Salvajes');

        $battle = new AgregadoBatalla($team1, $team2);
        $battle->triggerBattleStartEffects();

        $session->guardar($battleId, $battle);
        $session->guardarMeta($battleId, $meta);

        return [$battle, $battleId];
    }

    private function crearPokemonBatalla(int $id): \Src\Battle\Domain\DatosPokemonBatalla
    {
        return new \Src\Battle\Domain\DatosPokemonBatalla(
            id: 'batalla_'.$id,
            nombre: 'pokemon-'.$id,
            hp: 80,
            atk: 80,
            def: 80,
            spAtk: 80,
            spDef: 80,
            speed: 80,
            tipos: [\Src\Shared\Tipos\TipoPokemon::NORMAL],
            posicion: \Src\Battle\Domain\Posicion::VANGUARDIA,
            moves: [new \Src\Battle\Domain\MovimientoBatalla(
                nombre: 'Placaje',
                potencia: 50,
                tipo: \Src\Shared\Tipos\TipoPokemon::NORMAL,
                categoria: \Src\Battle\Domain\Enums\CategoriaMovimiento::FISICO,
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
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);

        foreach (StatEnum::cases() as $stat) {
            PokemonStat::create([
                'pokemon_id' => $pokemon->id,
                'stat' => $stat->value,
                'base_stat' => 80,
                'effort' => 0,
            ]);
        }

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
