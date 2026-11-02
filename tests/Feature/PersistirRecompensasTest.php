<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TipoEnum;
use App\Models\PlayerInventory;
use App\Models\PlayerTypePath;
use App\Models\Pokemon;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Src\Exploraciones\App\PersistirRecompensas;
use Src\Exploraciones\Domain\Recompensas\RecompensaEv;
use Src\Exploraciones\Domain\Recompensas\RecompensaFamilia;
use Src\Exploraciones\Domain\Recompensas\RecompensaTipo;
use Src\Exploraciones\Domain\Recompensas\ResultadoRecompensas;
use Tests\TestCase;

class PersistirRecompensasTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::factory()->create();
    }

    /**
     * @return array{equipo: Team, reclutado: Reclutado}
     */
    private function crearEquipoConReclutado(): array
    {
        $pokemon = Pokemon::create([
            'id' => 25,
            'name' => 'pikachu',
            'species_id' => 25,
            'capture_rate' => 190,
            'base_experience' => 112,
            'height' => 4,
            'weight' => 60,
        ]);

        $reclutado = Reclutado::create([
            'user_id' => $this->usuario->id,
            'nombre' => 'Pika',
            'pokemon_id' => $pokemon->id,
            'exp' => ['total' => 1250, 'tipos' => ['Fuego' => 100]],
            'es_shiny' => false,
            'obj_equipados' => [],
            'movimientos' => [],
        ]);

        $equipo = Team::create(['name' => 'Equipo Test', 'user_id' => $this->usuario->id]);
        TeamMember::create([
            'team_id' => $equipo->id,
            'pokemon_id' => $reclutado->id,
            'slot' => 1,
            'behavior' => 'VANGUARDIA',
        ]);

        return ['equipo' => $equipo, 'reclutado' => $reclutado];
    }

    /**
     * @param  array<string, int>  $expTipoPorMiembro
     */
    private function resultado(int $expTotal, int $expPorMiembro, array $expTipoPorMiembro = []): ResultadoRecompensas
    {
        return new ResultadoRecompensas(
            capturas: new Collection(),
            caramelosFamilia: new Collection(),
            caramelosEv: new Collection(),
            caramelosTipo: new Collection(),
            expTotal: $expTotal,
            expPorMiembro: $expPorMiembro,
            expTipoPorMiembro: $expTipoPorMiembro,
        );
    }

    public function test_persistir_acumula_exp_total_y_exp_tipo_en_el_reclutado(): void
    {
        $ctx = $this->crearEquipoConReclutado();

        (new PersistirRecompensas())->persistir(
            $this->resultado(expTotal: 500, expPorMiembro: 300, expTipoPorMiembro: ['Veneno' => 500]),
            $ctx['equipo'],
            $this->usuario,
        );

        $reclutado = $ctx['reclutado']->fresh();
        // El total del reclutado acumula la parte de miembro (no el 100 %).
        $this->assertSame(1250 + 300, $reclutado->exp->total());
        // La exp de tipo se acumula en exp.tipos.
        $this->assertSame(500, $reclutado->exp->expTipo('Veneno'));
        // Los tipos no implicados quedan intactos.
        $this->assertSame(100, $reclutado->exp->expTipo('Fuego'));
    }

    public function test_persistir_no_crea_exp_tipo_de_tipos_ausentes(): void
    {
        $ctx = $this->crearEquipoConReclutado();

        (new PersistirRecompensas())->persistir(
            $this->resultado(expTotal: 100, expPorMiembro: 60, expTipoPorMiembro: ['Veneno' => 500]),
            $ctx['equipo'],
            $this->usuario,
        );

        $reclutado = $ctx['reclutado']->fresh();
        $this->assertSame(500, $reclutado->exp->expTipo('Veneno'));
        $this->assertSame(0, $reclutado->exp->expTipo('Eléctrico'));
    }

    public function test_persistir_sin_exp_tipo_por_miembro_no_anade_tipos(): void
    {
        $ctx = $this->crearEquipoConReclutado();

        (new PersistirRecompensas())->persistir(
            $this->resultado(expTotal: 100, expPorMiembro: 60),
            $ctx['equipo'],
            $this->usuario,
        );

        $reclutado = $ctx['reclutado']->fresh();
        $this->assertSame(1250 + 60, $reclutado->exp->total());
        $this->assertSame(100, $reclutado->exp->expTipo('Fuego'));
        $this->assertSame(0, $reclutado->exp->expTipo('Veneno'));
    }

    public function test_persistir_economia_individual_reparte_la_exp_entre_cuenta_y_reclutado(): void
    {
        $ctx = $this->crearEquipoConReclutado();

        $recompensas = new ResultadoRecompensas(
            capturas: new Collection(),
            caramelosFamilia: new Collection(),
            caramelosEv: new Collection(),
            caramelosTipo: new Collection(),
            expTotal: 250,
            expPorMiembro: 250,
            expTipoPorMiembro: ['Fuego' => 125],
            expTipoCuenta: ['Fuego' => 125],
        );

        (new PersistirRecompensas())->persistir($recompensas, $ctx['reclutado'], $this->usuario);

        // Invariante de la economía: cuenta 250 + reclutado 250 = 500 (pool).
        $this->assertSame(250, $this->usuario->refresh()->experiencia);
        $reclutado = $ctx['reclutado']->fresh();
        $this->assertSame(1_250 + 250, $reclutado->exp->total());
        // El tipo se acumula UNA sola vez en cada lado (sin doble descuento).
        $this->assertSame(100 + 125, $reclutado->exp->expTipo('Fuego'));
        $this->assertSame(125, $this->expSenda(TipoEnum::FIRE));
    }

    public function test_persistir_sin_exp_tipo_cuenta_usa_fallback_legado(): void
    {
        $ctx = $this->crearEquipoConReclutado();

        $recompensas = new ResultadoRecompensas(
            capturas: new Collection(),
            caramelosFamilia: new Collection(),
            caramelosEv: new Collection(),
            caramelosTipo: new Collection(),
            expTotal: 500,
            expPorMiembro: 300,
            expTipoPorMiembro: ['Veneno' => 500],
            expTipoCuenta: null,
        );

        (new PersistirRecompensas())->persistir($recompensas, $ctx['equipo'], $this->usuario);

        // Legado: la cuenta usa la misma exp de tipo que cada miembro.
        $this->assertSame(500, $this->expSenda(TipoEnum::POISON));
        $this->assertSame(500, $ctx['reclutado']->fresh()->exp->expTipo('Veneno'));
    }

    public function test_persistir_ignora_caramelos_con_cantidad_cero(): void
    {
        $recompensas = new ResultadoRecompensas(
            capturas: new Collection(),
            caramelosFamilia: new Collection([new RecompensaFamilia(evolutionChainId: 51, cantidad: 0)]),
            caramelosEv: new Collection([new RecompensaEv(stat: 6, cantidad: 0)]),
            caramelosTipo: new Collection([new RecompensaTipo(tipo: 'Fuego', cantidad: 0)]),
            expTotal: 0,
        );

        (new PersistirRecompensas())->persistir($recompensas, null, $this->usuario);

        $this->assertSame(
            0,
            PlayerInventory::query()->where('user_id', $this->usuario->id)->count(),
            'No debe crearse ninguna fila de inventario con cantidad 0',
        );
    }

    public function test_persistir_incrementa_caramelos_con_cantidad_positiva(): void
    {
        $recompensas = new ResultadoRecompensas(
            capturas: new Collection(),
            caramelosFamilia: new Collection([new RecompensaFamilia(evolutionChainId: 51, cantidad: 2)]),
            caramelosEv: new Collection([new RecompensaEv(stat: 6, cantidad: 3)]),
            caramelosTipo: new Collection([new RecompensaTipo(tipo: 'Fuego', cantidad: 4)]),
            expTotal: 0,
        );

        (new PersistirRecompensas())->persistir($recompensas, null, $this->usuario);

        $inventario = PlayerInventory::query()
            ->where('user_id', $this->usuario->id)
            ->orderBy('cantidad')
            ->pluck('cantidad')
            ->all();

        $this->assertSame([2, 3, 4], $inventario);
    }

    private function expSenda(TipoEnum $tipo): int
    {
        return (int) PlayerTypePath::query()
            ->where('user_id', $this->usuario->id)
            ->where('type_id', $tipo->value)
            ->value('exp');
    }
}
