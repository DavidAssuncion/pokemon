<?php

declare(strict_types=1);

namespace Tests\Feature\CombateEntrenadores;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\PlayerTypePath;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Support\CargarMapaSendaJugador;
use App\Support\SendaProgreso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Src\Battle\Domain\EquipoBatalla;
use Src\CombateEntrenadores\App\ConstruirEquipoJugador;
use Src\CombateEntrenadores\App\MapeadorPokemonBatalla;
use Src\CombateEntrenadores\Domain\ValueObjects\FormacionEquipo;
use Src\Shared\Domain\ClasificadorOfensivaDefensiva;
use Src\Shared\Domain\Sendas\MapaSendaJugador;
use Src\Shared\Domain\Sendas\NivelSenda;
use Src\Shared\Tipos\TipoPokemon;
use Tests\TestCase;

class ConstruirEquipoJugadorSendaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    #[Test]
    public function desde_equipo_carga_el_mapa_de_sendas_del_dueno_y_lo_propaga(): void
    {
        $team = $this->crearEquipoDeTres();
        $this->crearProgresoSenda(TipoEnum::FIRE->value, 5);
        $this->crearProgresoSenda(TipoEnum::WATER->value, 4);

        $datos = $this->construir()->desdeEquipo($team, FormacionEquipo::vacia());

        $esperado = [TipoEnum::FIRE->value => 5, TipoEnum::WATER->value => 4];

        foreach ($datos->all() as $dato) {
            $this->assertNotNull($dato->senda);
            $this->assertSame($esperado, $dato->senda->toArray());
            $this->assertSame(NivelSenda::N5, $dato->senda->nivel(TipoPokemon::FUEGO));
        }

        $equipo = EquipoBatalla::fromData($datos, 'Jugador');

        foreach ($equipo->combatants() as $combatiente) {
            $this->assertSame($esperado, $combatiente->senda()->toArray());
        }
    }

    #[Test]
    public function desde_equipo_respeta_el_mapa_explicito_sin_consultar_la_base(): void
    {
        $team = $this->crearEquipoDeTres();
        $this->crearProgresoSenda(TipoEnum::FIRE->value, 5);

        $explicito = MapaSendaJugador::desdeArray([TipoPokemon::AGUA->value => 2]);

        $datos = $this->construir()->desdeEquipo($team, FormacionEquipo::vacia(), senda: $explicito);

        $this->assertSame([TipoPokemon::AGUA->value => 2], $datos->en(0)?->senda?->toArray());
    }

    #[Test]
    public function cargador_lee_el_usuario_indicado_aunque_haya_otro_autenticado(): void
    {
        $this->crearProgresoSenda(TipoEnum::FIRE->value, 5);
        $otro = User::factory()->create();

        $this->actingAs($otro);

        $mapa = app(CargarMapaSendaJugador::class)->para($this->user->id);

        $this->assertSame([TipoPokemon::FUEGO->value => 5], $mapa->toArray());
        $this->assertSame([], app(CargarMapaSendaJugador::class)->para($otro->id)->toArray());
    }

    private function construir(): ConstruirEquipoJugador
    {
        return new ConstruirEquipoJugador(
            app(MapeadorPokemonBatalla::class),
            new ClasificadorOfensivaDefensiva(),
        );
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

    private function crearEquipoDeTres(): Team
    {
        $team = Team::create(['name' => 'Equipo Sendas', 'user_id' => $this->user->id]);

        for ($slot = 1; $slot <= 3; $slot++) {
            $pokemon = $this->crearPokemon(8800 + $slot, 'pokemon-'.$slot);
            $reclutado = Reclutado::create([
                'user_id' => $this->user->id,
                'nombre' => $pokemon->name,
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

    private function crearPokemon(int $id, string $nombre): Pokemon
    {
        $pokemon = Pokemon::create([
            'id' => $id,
            'name' => $nombre,
            'species_id' => $id,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);

        $stats = [
            StatEnum::HP->value => 100,
            StatEnum::ATTACK->value => 100,
            StatEnum::DEFENSE->value => 60,
            StatEnum::SPECIAL_ATTACK->value => 60,
            StatEnum::SPECIAL_DEFENSE->value => 60,
            StatEnum::SPEED->value => 60,
        ];

        foreach ($stats as $stat => $valor) {
            PokemonStat::create([
                'pokemon_id' => $pokemon->id,
                'stat' => $stat,
                'base_stat' => $valor,
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
}
