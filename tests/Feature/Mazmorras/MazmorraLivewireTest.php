<?php

declare(strict_types=1);

namespace Tests\Feature\Mazmorras;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Livewire\Combate;
use App\Models\DungeonLog;
use App\Models\DungeonProgress;
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
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Src\Battle\Domain\AgregadoBatalla;
use Tests\TestCase;
use Tests\Unit\Battle\ConstruyeCombatientes;

/**
 * Al terminar una batalla de mazmorra, el progreso avanza si el jugador ganó
 * y se registra una derrota (cooldown 1h) si perdió.
 */
class MazmorraLivewireTest extends TestCase
{
    use ConstruyeCombatientes;
    use RefreshDatabase;

    private const SESSION_VERSION = 9;

    private User $user;

    private Team $team;

    private Habitat $habitat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['experiencia' => 10 * 20 ** 3]); // nivel 20
        $this->actingAs($this->user);
        $this->team = $this->crearEquipoJugador($this->user);
        $this->habitat = Habitat::create([
            'province_id' => Province::create(['name' => 'Provincia Test'])->id,
            'name' => 'Hábitat Mazmorra',
            'pokemons' => [],
            'peligro' => 3,
            'mazmorra' => ['pisos' => [['piso' => 1, 'species_id' => 25], ['piso' => 2, 'species_id' => 26]]],
        ]);
    }

    #[Test]
    public function test_al_ganar_avanza_progreso_y_no_hay_cooldown(): void
    {
        $pokemonJefe = $this->crearPokemonCompleto(25);

        $battle = $this->batallaConVictoriaJugador((int) $pokemonJefe->id);
        $battleId = $this->guardarBatallaMazmorra($battle);

        $component = $this->montarCombate($battleId);

        $component->assertSet('phase', 'battle_over');

        $progreso = DungeonProgress::query()->where('user_id', $this->user->id)->first();
        $this->assertNotNull($progreso);
        $this->assertSame(2, $progreso->current_floor);
        $this->assertNull($progreso->completed_at);
        $this->assertFalse(DungeonLog::query()->where('user_id', $this->user->id)->exists());
    }

    #[Test]
    public function test_al_ganar_ultimo_piso_marca_completada(): void
    {
        $pokemonJefe = $this->crearPokemonCompleto(26);
        DungeonProgress::create([
            'user_id' => $this->user->id,
            'habitat_id' => $this->habitat->id,
            'current_floor' => 2,
        ]);

        $battle = $this->batallaConVictoriaJugador((int) $pokemonJefe->id);
        $battleId = $this->guardarBatallaMazmorra($battle, 2);

        $component = $this->montarCombate($battleId);

        $component->assertSet('phase', 'battle_over');

        $progreso = DungeonProgress::query()->where('user_id', $this->user->id)->first();
        $this->assertNotNull($progreso->completed_at);
    }

    #[Test]
    public function test_al_perder_registra_derrota_y_no_avanza(): void
    {
        $pokemonJefe = $this->crearPokemonCompleto(25);

        $battle = $this->batallaConVictoriaRival((int) $pokemonJefe->id);
        $battleId = $this->guardarBatallaMazmorra($battle);

        $component = $this->montarCombate($battleId);

        $component->assertSet('phase', 'battle_over');

        $this->assertNull(DungeonProgress::query()->where('user_id', $this->user->id)->first());

        $derrota = DungeonLog::query()->where('user_id', $this->user->id)->first();
        $this->assertNotNull($derrota);
        $this->assertSame(1, $derrota->floor);
        $this->assertFalse($derrota->won);
    }

    /* istanbul ignore next */
    private function montarCombate(string $battleId): Testable
    {
        return Livewire::withQueryParams(['battle_id' => $battleId])->test(Combate::class);
    }

    private function guardarBatallaMazmorra(AgregadoBatalla $battle, int $floor = 1): string
    {
        $battleId = 'battle_mazmorra_test_'.uniqid();

        session()->put($battleId, self::SESSION_VERSION.'|'.serialize($battle));
        session()->put($battleId.'_meta', [
            'tipo' => 'mazmorra',
            'habitat_id' => $this->habitat->id,
            'floor' => $floor,
            'boss_species_id' => 25,
            'nivel_rival' => 20,
            'user_id' => (int) $this->user->id,
            'team_id' => (int) $this->team->id,
        ]);

        return $battleId;
    }

    /** team1 (jugador) gana: rival totalmente debilitado. */
    private function batallaConVictoriaJugador(int $speciesRival): AgregadoBatalla
    {
        $atacante = $this->combatiente(
            stats: ['hp' => 200, 'atk' => 200, 'def' => 200, 'spAtk' => 200, 'spDef' => 200, 'speed' => 200],
            id: 'jugador_1',
            nombre: 'Jugador',
        );
        $atacante->setSpeciesId(1001);

        $defensor = $this->combatiente(
            stats: ['hp' => 1, 'atk' => 10, 'def' => 10, 'spAtk' => 10, 'spDef' => 10, 'speed' => 1],
            id: 'rival_1',
            nombre: 'Rival',
        );
        $defensor->setSpeciesId($speciesRival);
        $defensor->setHpActual(0);

        return $this->batallaMinima($atacante, $defensor);
    }

    /** team2 (rival) gana: jugador totalmente debilitado. */
    private function batallaConVictoriaRival(int $speciesRival): AgregadoBatalla
    {
        $atacante = $this->combatiente(
            stats: ['hp' => 1, 'atk' => 10, 'def' => 10, 'spAtk' => 10, 'spDef' => 10, 'speed' => 1],
            id: 'jugador_1',
            nombre: 'Jugador',
        );
        $atacante->setSpeciesId(1001);
        $atacante->setHpActual(0);

        $defensor = $this->combatiente(
            stats: ['hp' => 200, 'atk' => 200, 'def' => 200, 'spAtk' => 200, 'spDef' => 200, 'speed' => 200],
            id: 'rival_1',
            nombre: 'Rival',
        );
        $defensor->setSpeciesId($speciesRival);

        return $this->batallaMinima($atacante, $defensor);
    }

    private function crearPokemonCompleto(int $speciesId): Pokemon
    {
        $pokemon = Pokemon::create([
            'id' => $speciesId,
            'name' => 'pokemon-'.$speciesId,
            'species_id' => $speciesId,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);

        PokemonStat::create(['pokemon_id' => $pokemon->id, 'stat' => StatEnum::HP->value, 'base_stat' => 50, 'effort' => 0]);
        PokemonStat::create(['pokemon_id' => $pokemon->id, 'stat' => StatEnum::ATTACK->value, 'base_stat' => 60, 'effort' => 0]);
        PokemonType::create(['pokemon_id' => $pokemon->id, 'type' => TipoEnum::NORMAL, 'slot' => 1]);

        return $pokemon;
    }

    private function crearEquipoJugador(User $user): Team
    {
        $team = Team::create(['name' => 'Equipo Test', 'user_id' => $user->id]);

        foreach ([1, 2, 3] as $slot) {
            $pokemon = Pokemon::create([
                'id' => 1000 + $slot,
                'name' => 'jugador-'.$slot,
                'species_id' => 1000 + $slot,
                'capture_rate' => 45,
                'base_experience' => 64,
                'height' => 7,
                'weight' => 69,
            ]);

            foreach (StatEnum::cases() as $stat) {
                PokemonStat::create(['pokemon_id' => $pokemon->id, 'stat' => $stat->value, 'base_stat' => 100, 'effort' => 0]);
            }

            PokemonType::create(['pokemon_id' => $pokemon->id, 'type' => TipoEnum::NORMAL, 'slot' => 1]);

            $reclutado = Reclutado::create([
                'user_id' => $user->id,
                'nombre' => 'jugador-'.$slot,
                'pokemon_id' => $pokemon->id,
                'exp' => ['exp' => 100],
                'obj_equipados' => [],
                'movimientos' => [],
            ]);

            TeamMember::create([
                'team_id' => $team->id,
                'pokemon_id' => $reclutado->id,
                'slot' => $slot,
                'behavior' => 'VANGUARDIA',
            ]);
        }

        return $team;
    }
}
