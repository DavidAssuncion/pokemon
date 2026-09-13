<?php

declare(strict_types=1);

namespace Tests\Feature\CombateRuta;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\Province;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Support\BattleSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Src\Battle\Domain\AgregadoBatalla;
use Src\CombateEntrenadores\App\ConstruirEquipoJugador;
use Src\CombateEntrenadores\App\MapeadorPokemonBatalla;
use Src\CombateEntrenadores\Domain\GeneradorMovimientosTipo;
use Src\CombateRuta\App\GeneradorEquipoRuta;
use Src\CombateRuta\App\IniciarCombateRuta;
use Src\CombateRuta\Domain\ClasificadorOfensivaDefensiva;
use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;
use Tests\TestCase;

class IniciarCombateRutaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Habitat $habitat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['experiencia' => 10 * 20 ** 3]);
        $this->habitat = $this->crearHabitat('Ruta Norte');
    }

    #[Test]
    public function crea_batalla_5v5_contra_salvajes_y_guarda_meta_ruta(): void
    {
        for ($id = 1; $id <= 6; $id++) {
            $this->crearPokemonDelPool($id, captureRate: 200);
        }
        $equipo = $this->crearEquipo(5);

        $iniciar = $this->iniciador();
        $battleId = $iniciar->iniciar(
            habitatId: (int) $this->habitat->id,
            nivel: 1,
            teamId: (int) $equipo->id,
            userId: (int) $this->user->id,
            nivelJugador: 12,
        );

        $session = app(BattleSessionService::class);
        $battle = $session->cargar($battleId);

        $this->assertInstanceOf(AgregadoBatalla::class, $battle);
        $this->assertCount(5, $battle->team1->combatientesCollection());
        $this->assertCount(5, $battle->team2->combatientesCollection());

        $meta = $session->cargarMeta($battleId);
        $this->assertSame('ruta', $meta['tipo']);
        $this->assertSame((int) $this->habitat->id, $meta['habitat_id']);
        $this->assertSame((int) $equipo->id, $meta['team_id']);
        $this->assertSame((int) $this->user->id, $meta['user_id']);
    }

    #[Test]
    public function team_con_menos_de_cinco_miembros_lanza_error(): void
    {
        for ($id = 1; $id <= 5; $id++) {
            $this->crearPokemonDelPool($id, captureRate: 200);
        }
        $equipo = $this->crearEquipo(4);

        $this->expectException(ViolacionReglaNegocio::class);
        $this->expectExceptionMessage('5 miembros');

        $this->iniciador()->iniciar(
            habitatId: (int) $this->habitat->id,
            nivel: 1,
            teamId: (int) $equipo->id,
            userId: (int) $this->user->id,
            nivelJugador: 12,
        );
    }

    #[Test]
    public function pool_vacio_en_el_nivel_lanza_error_claro(): void
    {
        // El pool solo existe en nivel 2; se pide nivel 1 → sin rivales.
        $this->crearPokemonDelPool(1, captureRate: 200, nivel: 2);
        $equipo = $this->crearEquipo(5);

        $this->expectException(ViolacionReglaNegocio::class);
        $this->expectExceptionMessage('No hay Pokémon salvajes');

        $this->iniciador()->iniciar(
            habitatId: (int) $this->habitat->id,
            nivel: 1,
            teamId: (int) $equipo->id,
            userId: (int) $this->user->id,
            nivelJugador: 12,
        );
    }

    #[Test]
    public function repetible_sin_limite_diario(): void
    {
        for ($id = 1; $id <= 5; $id++) {
            $this->crearPokemonDelPool($id, captureRate: 200);
        }
        $equipo = $this->crearEquipo(5);
        $session = app(BattleSessionService::class);
        $iniciar = $this->iniciador();

        $battleId1 = $iniciar->iniciar(
            habitatId: (int) $this->habitat->id,
            nivel: 1,
            teamId: (int) $equipo->id,
            userId: (int) $this->user->id,
            nivelJugador: 12,
        );
        $battleId2 = $iniciar->iniciar(
            habitatId: (int) $this->habitat->id,
            nivel: 1,
            teamId: (int) $equipo->id,
            userId: (int) $this->user->id,
            nivelJugador: 12,
        );

        $this->assertNotSame($battleId1, $battleId2);
        $this->assertNotNull($session->cargar($battleId1));
        $this->assertNotNull($session->cargar($battleId2));
    }

    private function iniciador(): IniciarCombateRuta
    {
        $mapeador = new MapeadorPokemonBatalla(new GeneradorMovimientosTipo());
        $clasificador = new ClasificadorOfensivaDefensiva();

        return new IniciarCombateRuta(
            new GeneradorEquipoRuta($mapeador, $clasificador),
            new ConstruirEquipoJugador($mapeador, $clasificador),
            app(BattleSessionService::class),
        );
    }

    private function crearHabitat(string $nombre): Habitat
    {
        $province = Province::create(['name' => 'Kanto']);

        return Habitat::create(['province_id' => $province->id, 'name' => $nombre, 'peligro' => 1]);
    }

    private function crearPokemonDelPool(int $id, int $captureRate, int $nivel = 1): Pokemon
    {
        $pokemon = Pokemon::create([
            'id' => $id,
            'name' => 'salvaje-'.$id,
            'species_id' => $id,
            'capture_rate' => $captureRate,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
            'hatch' => 10,
        ]);

        foreach (StatEnum::cases() as $stat) {
            $pokemon->stats()->create([
                'stat' => $stat->value,
                'base_stat' => 80,
                'effort' => 0,
            ]);
        }

        $pokemon->types()->create([
            'type' => TipoEnum::NORMAL,
            'slot' => 1,
        ]);

        $this->habitat->pokemon()->attach($pokemon->id, ['level' => $nivel]);

        return $pokemon;
    }

    private function crearEquipo(int $miembros): Team
    {
        $team = Team::create(['name' => 'Equipo Ruta', 'user_id' => $this->user->id]);

        for ($slot = 1; $slot <= $miembros; $slot++) {
            $pokemon = Pokemon::create([
                'id' => 9000 + $slot,
                'name' => 'jugador-'.$slot,
                'species_id' => 9000 + $slot,
                'capture_rate' => 45,
                'base_experience' => 64,
                'height' => 7,
                'weight' => 69,
            ]);

            foreach (StatEnum::cases() as $stat) {
                $pokemon->stats()->create([
                    'stat' => $stat->value,
                    'base_stat' => 100,
                    'effort' => 0,
                ]);
            }

            $pokemon->types()->create([
                'type' => TipoEnum::NORMAL,
                'slot' => 1,
            ]);

            $reclutado = Reclutado::create([
                'user_id' => $this->user->id,
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
