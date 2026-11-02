<?php

declare(strict_types=1);

namespace Tests\Feature\CombateEntrenadores;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Livewire\Presenters\PresentadorResultadoBatalla;
use App\Models\Habitat;
use App\Models\PlayerTypePath;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Support\BattleSessionService;
use App\Support\CargarMapaSendaJugador;
use App\Support\SendaProgreso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Collections\DatosPokemonBatallaCollection;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\Posicion;
use Src\CombateEntrenadores\App\OtorgarRecompensasEntrenador;
use Src\CombateEntrenadores\Domain\DataTransferObjects\DatosModalVictoria;
use Src\Exploraciones\App\PersistirRecompensas;
use Src\Exploraciones\Domain\CalculadorRecompensas;
use Src\Shared\Domain\DataTransferObjects\ItemCaramelo;
use Src\Shared\Domain\Sendas\MapaSendaJugador;
use Src\Shared\Tipos\TipoPokemon;
use Tests\TestCase;

/**
 * Senda aplicada a las recompensas de entrenador.
 *
 * Rival Normal con base_experience 6400 y usuario nivel 20:
 * expDerrota = intdiv(6400 × 20, 5) = 25.600.
 * Caramelo de tipo base (×2 entrenador): floor(25.600 × 0,2 / 100 × 2) = 102.
 * Senda N5 Normal (×1,2): floor(25.600 × 0,2 / 100 × 2 × 1,2) = 122.
 */
class SendaRecompensasEntrenadorTest extends TestCase
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
    public function senda_cinco_multiplica_el_caramelo_de_tipo_del_entrenador(): void
    {
        $pokemon = $this->crearPokemonRival(8101);

        // Sin Senda: la recompensa de tipo se queda en la base.
        $sinSenda = $this->otorgarEntrenador($pokemon);

        $this->crearProgresoSenda(TipoEnum::NORMAL->value, 5);
        $senda = app(CargarMapaSendaJugador::class)->para((int) $this->user->id);

        // Con Senda N5 del tipo del rival: ×1,2.
        $conSenda = $this->otorgarEntrenador($pokemon, $senda);

        $this->assertSame(102, $this->carameloTipo($sinSenda));
        $this->assertSame(122, $this->carameloTipo($conSenda));
    }

    #[Test]
    public function el_presentador_aplica_la_senda_al_entrenador(): void
    {
        Bus::fake();

        $this->crearProgresoSenda(TipoEnum::NORMAL->value, 5);
        $pokemon = $this->crearPokemonRival(8102);
        $habitat = Habitat::factory()->create();

        [$battle, $battleId] = $this->crearBatalla((int) $pokemon->id, [
            'tipo' => 'entrenador',
            'habitat_id' => (int) $habitat->id,
            'nivel' => 1,
            'trainer_index' => 0,
            'user_id' => (int) $this->user->id,
            'team_id' => (int) $this->team->id,
        ]);

        $resultado = PresentadorResultadoBatalla::procesar(
            $battle,
            $battleId,
            app(BattleSessionService::class),
        );

        $this->assertSame(122, $this->carameloTipoDeArray($resultado['rewards']));
    }

    private function otorgarEntrenador(Pokemon $pokemon, ?MapaSendaJugador $senda = null): DatosModalVictoria
    {
        $persistir = $this->createMock(PersistirRecompensas::class);
        $persistir->expects($this->once())->method('persistir');

        $otorgar = new OtorgarRecompensasEntrenador(new CalculadorRecompensas(), $persistir);

        $datos = $otorgar->otorgar(
            userId: (int) $this->user->id,
            teamId: (int) $this->team->id,
            speciesIdsRival: [(int) $pokemon->id],
            nivelEntrenador: 1,
            senda: $senda,
        );

        $this->assertInstanceOf(DatosModalVictoria::class, $datos);

        return $datos;
    }

    private function carameloTipo(DatosModalVictoria $datos): int
    {
        $caramelo = $datos->caramelos->first(
            fn (ItemCaramelo $item): bool => $item->nombre === TipoPokemon::NORMAL->label(),
        );

        $this->assertInstanceOf(ItemCaramelo::class, $caramelo);

        return $caramelo->cantidad;
    }

    /**
     * @param  array<string, mixed>  $rewards
     */
    private function carameloTipoDeArray(array $rewards): int
    {
        foreach ($rewards['caramelos'] ?? [] as $caramelo) {
            if (($caramelo['nombre'] ?? null) === TipoPokemon::NORMAL->label()) {
                return (int) $caramelo['cantidad'];
            }
        }

        $this->fail('No se encontró el caramelo de tipo Normal en las recompensas.');
    }

    private function crearProgresoSenda(int $tipoId, int $nivel): PlayerTypePath
    {
        return PlayerTypePath::query()->create([
            'user_id' => $this->user->id,
            'type_id' => $tipoId,
            'exp' => SendaProgreso::umbral($nivel),
            'level' => $nivel,
        ]);
    }

    private function crearPokemonRival(int $speciesId): Pokemon
    {
        $pokemon = Pokemon::create([
            'id' => $speciesId,
            'name' => 'rival-'.$speciesId,
            'species_id' => $speciesId,
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
            'id' => 8201,
            'name' => 'jugador-1',
            'species_id' => 8201,
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

    /**
     * @param  array<string, mixed>  $meta
     * @return array{0: AgregadoBatalla, 1: string}
     */
    private function crearBatalla(int $rivalSpeciesId, array $meta): array
    {
        $session = app(BattleSessionService::class);
        $battleId = 'battle_senda_entrenador_'.uniqid();

        $team1 = EquipoBatalla::fromData(
            new DatosPokemonBatallaCollection([$this->datosCombate(8202, 'Jugador')]),
            'Jugador',
        );
        $team2 = EquipoBatalla::fromData(
            new DatosPokemonBatallaCollection([$this->datosCombate($rivalSpeciesId, 'Rival')]),
            'Rival',
        );

        $battle = new AgregadoBatalla($team1, $team2);
        $battle->triggerBattleStartEffects();

        $session->guardar($battleId, $battle);
        $session->guardarMeta($battleId, $meta);

        return [$battle, $battleId];
    }

    private function datosCombate(int $speciesId, string $nombre): DatosPokemonBatalla
    {
        return new DatosPokemonBatalla(
            id: 'combate_'.$speciesId,
            nombre: $nombre,
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
            speciesId: $speciesId,
        );
    }
}
