<?php

declare(strict_types=1);

namespace Tests\Feature\Gimnasios;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Livewire\Combate;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
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

class GimnasioLivewireTest extends TestCase
{
    use ConstruyeCombatientes;
    use RefreshDatabase;

    private const SESSION_VERSION = 6;

    private User $user;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['experiencia' => 10 * 20 ** 3]); // nivel 20
        $this->actingAs($this->user);
        $this->team = $this->crearEquipoJugador($this->user);
    }

    #[Test]
    public function test_al_ganar_registra_progreso_y_recompensas(): void
    {
        $pokemonRival = $this->crearPokemonCompleto(268);

        $battle = $this->batallaConVictoriaJugador((int) $pokemonRival->id);
        $battleId = $this->guardarBatallaGimnasio($battle, 'bug', 1);

        $component = $this->montarCombate($battleId);

        $component->assertSet('phase', 'battle_over');

        $this->assertSame(2, $this->obtenerProgreso('bug'));
        $this->assertNotEmpty($this->leerRewards($component));
    }

    #[Test]
    public function test_al_ganar_lider_indica_medalla(): void
    {
        $pokemonRival = $this->crearPokemonCompleto(213);

        // Avanza hasta el líder
        $repositorio = $this->app->make(\Src\Gimnasios\Domain\Repositories\GymProgressRepositoryInterface::class);
        $repositorio->registrarVictoria((int) $this->user->id, 'bug', 1);
        $repositorio->registrarVictoria((int) $this->user->id, 'bug', 2);
        $repositorio->registrarVictoria((int) $this->user->id, 'bug', 3);

        $battle = $this->batallaConVictoriaJugador((int) $pokemonRival->id);
        $battleId = $this->guardarBatallaGimnasio($battle, 'bug', 4);

        $component = $this->montarCombate($battleId);

        $component->assertSet('phase', 'battle_over');

        $this->assertSame(5, $this->obtenerProgreso('bug'));
        $rewards = $this->leerRewards($component);
        $this->assertArrayHasKey('medalla', $rewards);
        $this->assertSame('Medalla Bicho', $rewards['medalla']);
    }

    #[Test]
    public function test_idor_no_avanza_progreso(): void
    {
        $pokemonRival = $this->crearPokemonCompleto(268);

        $battle = $this->batallaConVictoriaJugador((int) $pokemonRival->id);
        $battleId = $this->guardarBatallaGimnasio($battle, 'bug', 1);

        // meta.user_id != Auth::id() (se forja la batalla de otro usuario)
        session()->put($battleId.'_meta', [
            'tipo' => 'gimnasio',
            'gym_id' => 'bug',
            'stage' => 1,
            'nivel_rival' => 15,
            'user_id' => (int) $this->user->id + 999,
            'team_id' => (int) $this->team->id,
        ]);

        $component = $this->montarCombate($battleId);

        $component->assertSet('phase', 'battle_over');

        $this->assertNull($this->obtenerProgreso('bug'));
        $this->assertEmpty($this->leerRewards($component));
    }

    #[Test]
    public function test_al_perder_no_avanza_progreso(): void
    {
        $pokemonRival = $this->crearPokemonCompleto(268);

        $battle = $this->batallaConVictoriaRival((int) $pokemonRival->id);
        $battleId = $this->guardarBatallaGimnasio($battle, 'bug', 1);

        $component = $this->montarCombate($battleId);

        $component->assertSet('phase', 'battle_over');

        $this->assertNull($this->obtenerProgreso('bug'));
        $this->assertEmpty($this->leerRewards($component));
    }

    private function montarCombate(string $battleId): Testable
    {
        return Livewire::withQueryParams(['battle_id' => $battleId])->test(Combate::class);
    }

    /** @return array<string, mixed> */
    private function leerRewards(Testable $component): array
    {
        $property = new \ReflectionProperty(Combate::class, 'rewards');
        $property->setAccessible(true);

        /** @var array<string, mixed> $rewards */
        $rewards = $property->getValue($component->instance());

        return $rewards;
    }

    private function guardarBatallaGimnasio(AgregadoBatalla $battle, string $gymId, int $stage): string
    {
        $battleId = 'battle_gimnasio_test_'.uniqid();

        session()->put($battleId, self::SESSION_VERSION.'|'.serialize($battle));
        session()->put($battleId.'_meta', [
            'tipo' => 'gimnasio',
            'gym_id' => $gymId,
            'stage' => $stage,
            'nivel_rival' => 15,
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

    private function obtenerProgreso(string $gymId): ?int
    {
        return $this->app->make(\Src\Gimnasios\Domain\Repositories\GymProgressRepositoryInterface::class)
            ->obtenerProgreso((int) $this->user->id, $gymId);
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

        PokemonStat::create([
            'pokemon_id' => $pokemon->id,
            'stat' => StatEnum::HP->value,
            'base_stat' => 50,
            'effort' => 0,
        ]);
        PokemonStat::create([
            'pokemon_id' => $pokemon->id,
            'stat' => StatEnum::ATTACK->value,
            'base_stat' => 60,
            'effort' => 0,
        ]);

        PokemonType::create([
            'pokemon_id' => $pokemon->id,
            'type' => TipoEnum::NORMAL,
            'slot' => 1,
        ]);

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
