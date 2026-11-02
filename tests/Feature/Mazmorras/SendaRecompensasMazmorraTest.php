<?php

declare(strict_types=1);

namespace Tests\Feature\Mazmorras;

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
use App\Support\SendaProgreso;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
use Src\Mazmorras\App\OtorgarRecompensasMazmorra;
use Src\Shared\Domain\DataTransferObjects\ItemCaramelo;
use Src\Shared\Tipos\TipoPokemon;
use Tests\TestCase;

/**
 * Mazmorra sin Senda: aunque el jugador tenga N5 del tipo del jefe, el
 * otorgador de mazmorra no recibe el mapa y el caramelo de tipo no se
 * multiplica.
 *
 * Rival Normal con base_experience 6400 y usuario nivel 20:
 * expDerrota = 25.600; caramelo base de mazmorra (×70):
 * floor(25.600 × 0,2 / 100 × 70) = 3.584.
 * Con Senda N5 aplicada sería floor(3.584 × 1,2) = 4.300.
 */
class SendaRecompensasMazmorraTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function con_senda_cinco_del_tipo_del_jefe_el_caramelo_de_tipo_no_se_multiplica(): void
    {
        $conSenda = User::factory()->create(['experiencia' => 10 * 20 ** 3]);
        $sinSenda = User::factory()->create(['experiencia' => 10 * 20 ** 3]);

        $this->crearProgresoSenda($conSenda, TipoEnum::NORMAL->value, 5);
        $teamConSenda = Team::create(['name' => 'Con Senda', 'user_id' => $conSenda->id]);
        $teamSinSenda = Team::create(['name' => 'Sin Senda', 'user_id' => $sinSenda->id]);
        $pokemon = $this->crearPokemonRival(8501);
        [$battle] = $this->crearBatalla((int) $pokemon->id);

        $recompensaConSenda = $this->otorgarMazmorra($conSenda, $teamConSenda, $battle);
        $recompensaSinSenda = $this->otorgarMazmorra($sinSenda, $teamSinSenda, $battle);

        $this->assertSame(3584, $this->carameloTipo($recompensaConSenda));
        $this->assertSame(3584, $this->carameloTipo($recompensaSinSenda));
        $this->assertNotSame(4300, $this->carameloTipo($recompensaConSenda));
    }

    #[Test]
    public function el_presentador_de_mazmorra_no_aplica_la_senda(): void
    {
        $user = User::factory()->create(['experiencia' => 10 * 20 ** 3]);
        $team = $this->crearEquipo($user);
        $this->crearProgresoSenda($user, TipoEnum::NORMAL->value, 5);
        $pokemon = $this->crearPokemonRival(8502);
        $habitat = Habitat::factory()->create();

        [$battle, $battleId] = $this->crearBatalla((int) $pokemon->id, [
            'tipo' => 'mazmorra',
            'habitat_id' => (int) $habitat->id,
            'floor' => 1,
            'boss_species_id' => (int) $pokemon->id,
            'nivel_rival' => 20,
            'user_id' => (int) $user->id,
            'team_id' => (int) $team->id,
        ]);

        $resultado = PresentadorResultadoBatalla::procesar(
            $battle,
            $battleId,
            app(BattleSessionService::class),
        );

        $this->assertSame(3584, $this->carameloTipoDeArray($resultado['rewards']));
    }

    private function otorgarMazmorra(User $user, Team $team, AgregadoBatalla $battle): DatosModalVictoria
    {
        $persistir = $this->createMock(PersistirRecompensas::class);
        $persistir->expects($this->once())->method('persistir');

        $otorgar = new OtorgarRecompensasMazmorra(
            new OtorgarRecompensasEntrenador(new CalculadorRecompensas(), $persistir),
        );

        $datos = $otorgar->otorgar(
            userId: (int) $user->id,
            teamId: (int) $team->id,
            battle: $battle,
            nivel: 20,
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

    private function crearProgresoSenda(User $user, int $tipoId, int $nivel): PlayerTypePath
    {
        return PlayerTypePath::query()->create([
            'user_id' => $user->id,
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

    private function crearEquipo(User $user): Team
    {
        $team = Team::create(['name' => 'Equipo Test', 'user_id' => $user->id]);

        $pokemon = Pokemon::create([
            'id' => 8601,
            'name' => 'jugador-1',
            'species_id' => 8601,
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
    private function crearBatalla(int $rivalSpeciesId, array $meta = []): array
    {
        $session = app(BattleSessionService::class);
        $battleId = 'battle_senda_mazmorra_'.uniqid();

        $team1 = EquipoBatalla::fromData(
            new DatosPokemonBatallaCollection([$this->datosCombate(8602, 'Jugador')]),
            'Jugador',
        );
        $team2 = EquipoBatalla::fromData(
            new DatosPokemonBatallaCollection([$this->datosCombate($rivalSpeciesId, 'Jefe')]),
            'Jefe',
        );

        $battle = new AgregadoBatalla($team1, $team2);
        $battle->triggerBattleStartEffects();

        $session->guardar($battleId, $battle);

        if ($meta !== []) {
            $session->guardarMeta($battleId, $meta);
        }

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
