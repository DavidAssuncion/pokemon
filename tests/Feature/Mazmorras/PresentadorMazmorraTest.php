<?php

declare(strict_types=1);

namespace Tests\Feature\Mazmorras;

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
use Src\Battle\Domain\Collections\DatosPokemonBatallaCollection;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\Posicion;
use Src\Mazmorras\Domain\ReglasMazmorra;
use Src\Shared\Tipos\TipoPokemon;
use Tests\TestCase;

class PresentadorMazmorraTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function la_recompensa_de_mazmorra_es_siete_veces_la_de_gimnasio(): void
    {
        $this->assertSame(7, ReglasMazmorra::FACTOR_RECOMPENSA_RESPECTO_GIMNASIO);
        $this->assertSame(
            70.0,
            \Src\CombateEntrenadores\App\OtorgarRecompensasEntrenador::MULTIPLICADOR_GIMNASIO
                * ReglasMazmorra::FACTOR_RECOMPENSA_RESPECTO_GIMNASIO,
        );
    }

    #[Test]
    public function victoria_otorga_recompensas(): void
    {
        $user = User::factory()->create(['experiencia' => 10 * 20 ** 3]);
        $team = $this->crearEquipo($user);
        $boss = $this->crearPokemonRival(7001);

        [$battle, $battleId] = $this->crearBatalla($user, $team, $boss, 1);

        // Victoria: el equipo del jugador sigue vivo; el jefe cae.
        foreach ($battle->team2->combatientesCollection()->all() as $combatiente) {
            $combatiente->setHpActual(0);
        }

        $resultado = PresentadorResultadoBatalla::procesar($battle, $battleId, app(BattleSessionService::class));

        $this->assertNotEmpty($resultado['rewards'], 'La victoria de mazmorra debe otorgar recompensas');
        $this->assertArrayHasKey('exp_total', $resultado['rewards']);
    }

    #[Test]
    public function derrota_no_otorga_recompensas(): void
    {
        $user = User::factory()->create(['experiencia' => 10 * 20 ** 3]);
        $team = $this->crearEquipo($user);
        $boss = $this->crearPokemonRival(7002);

        [$battle, $battleId] = $this->crearBatalla($user, $team, $boss, 1);

        foreach ($battle->team1->combatientesCollection()->all() as $combatiente) {
            $combatiente->setHpActual(0);
        }

        $resultado = PresentadorResultadoBatalla::procesar($battle, $battleId, app(BattleSessionService::class));

        $this->assertSame([], $resultado['rewards']);
    }

    private function crearBatalla(User $user, Team $team, Pokemon $boss, int $piso): array
    {
        $pokemonJugador = $this->datosCombate(9001, 'jugador', 80);
        $pokemonJefe = $this->datosCombate($boss->id, 'jefe', 3430);

        $session = app(BattleSessionService::class);
        $battleId = 'battle_mazmorra_test_'.uniqid();

        $team1 = EquipoBatalla::fromData(new DatosPokemonBatallaCollection([$pokemonJugador]), 'Jugador');
        $team2 = EquipoBatalla::fromData(new DatosPokemonBatallaCollection([$pokemonJefe]), 'Jefe');

        $battle = new AgregadoBatalla($team1, $team2);
        $battle->triggerBattleStartEffects();

        $session->guardar($battleId, $battle);
        $session->guardarMeta($battleId, [
            'tipo' => 'mazmorra',
            'habitat_id' => 1,
            'floor' => $piso,
            'boss_species_id' => $boss->id,
            'nivel_rival' => 20,
            'user_id' => (int) $user->id,
            'team_id' => (int) $team->id,
        ]);

        return [$battle, $battleId];
    }

    private function datosCombate(int $speciesId, string $nombre, int $hp): DatosPokemonBatalla
    {
        return new DatosPokemonBatalla(
            id: 'combate_'.$speciesId,
            nombre: $nombre,
            hp: $hp,
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
            speciesId: $speciesId,
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

    private function crearEquipo(User $user): Team
    {
        $team = Team::create(['name' => 'Equipo Test', 'user_id' => $user->id]);

        $pokemon = Pokemon::create([
            'id' => 9601,
            'name' => 'jugador-1',
            'species_id' => 9601,
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
            'user_id' => $user->id,
            'nombre' => 'Jugador',
            'pokemon_id' => $pokemon->id,
            'exp' => ['total' => 100],
            'obj_equipados' => [],
            'movimientos' => [],
            'behavior' => 'VANGUARDIA',
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
