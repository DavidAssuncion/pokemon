<?php

declare(strict_types=1);

namespace Tests\Unit\Battle;

use PHPUnit\Framework\TestCase;
use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\Chain\CadenaDanio;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\TipoClima;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\Posicion;
use Src\Battle\Domain\ServicioEjecucionBatalla;
use Src\Battle\Presentation\DTOResultadoDanio;
use Src\Shared\Tipos\TipoPokemon;

/**
 * ServicioEjecucionBatalla: cálculo de daño, estados, stat changes y log.
 */
class ServicioEjecucionBatallaTest extends TestCase
{
    use ConstruyeCombatientes;

    private ServicioEjecucionBatalla $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servicio = new ServicioEjecucionBatalla(new CadenaDanio());
    }

    public function test_calcular_yaplicar_dano_retorna_dto_con_dano_mayor_que_cero(): void
    {
        $atacante = $this->combatiente(
            stats: ['atk' => 100, 'def' => 100],
            moves: [new MovimientoBatalla('Golpe', 50, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
            tipos: [TipoPokemon::VENENO],
            id: 'a1',
            nombre: 'Atacante',
        );
        $defensor = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 80, 'def' => 100],
            tipos: [TipoPokemon::AGUA],
            id: 'd1',
            nombre: 'Defensor',
        );

        $accion = new AccionBatalla(
            attacker: $atacante,
            defender: $defensor,
            move: new MovimientoBatalla('Golpe', 50, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO),
            fromPosition: Posicion::VANGUARDIA,
            defenderTeamHasVanguard: false,
            weather: TipoClima::NONE,
        );

        mt_srand(1); // sin crítico
        $resultado = $this->servicio->calcularYAplicarDano($accion);

        $this->assertInstanceOf(DTOResultadoDanio::class, $resultado);
        $this->assertGreaterThan(0, $resultado->dano);
        $this->assertSame(0.0, $resultado->directPct);
        // El daño debe haberse aplicado al defensor (barrera física)
        $this->assertLessThan(
            $defensor->pokemon()->battleStats()->defenseHp,
            $defensor->defensaHpActual(),
        );
    }

    public function test_aplicar_estado_no_aplica_si_objetivo_muerto(): void
    {
        $objetivo = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 80, 'def' => 100],
            tipos: [TipoPokemon::AGUA],
            id: 'd1',
            nombre: 'Objetivo',
        );
        $objetivo->setHpActual(0);

        $movimiento = new MovimientoBatalla(
            'Fuego Fatuo',
            0,
            TipoPokemon::FUEGO,
            CategoriaMovimiento::ESPECIAL,
            statusEffect: EstadoPokemon::BURN,
        );

        $this->servicio->aplicarEstado($objetivo, $movimiento);

        $this->assertSame(EstadoPokemon::NONE, $objetivo->estado());
    }

    public function test_aplicar_estado_sleep_establece_turnos_2_a_4(): void
    {
        $objetivo = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 80, 'def' => 100],
            tipos: [TipoPokemon::AGUA],
            id: 'd1',
            nombre: 'Objetivo',
        );
        $movimiento = new MovimientoBatalla(
            'Espora',
            0,
            TipoPokemon::PLANTA,
            CategoriaMovimiento::ESTADO,
            statusEffect: EstadoPokemon::SLEEP,
        );

        $this->servicio->aplicarEstado($objetivo, $movimiento);

        $this->assertSame(EstadoPokemon::SLEEP, $objetivo->estado());
        $this->assertGreaterThanOrEqual(2, $objetivo->turnosEstado());
        $this->assertLessThanOrEqual(4, $objetivo->turnosEstado());
    }

    public function test_aplicar_estado_confusion_establece_turnos_2_a_4(): void
    {
        $objetivo = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 80, 'def' => 100],
            tipos: [TipoPokemon::AGUA],
            id: 'd1',
            nombre: 'Objetivo',
        );
        $movimiento = new MovimientoBatalla(
            'Confusión',
            0,
            TipoPokemon::PSIQUICO,
            CategoriaMovimiento::ESTADO,
            statusEffect: EstadoPokemon::CONFUSION,
        );

        $this->servicio->aplicarEstado($objetivo, $movimiento);

        $this->assertSame(EstadoPokemon::CONFUSION, $objetivo->estado());
        $this->assertGreaterThanOrEqual(2, $objetivo->turnosEstado());
        $this->assertLessThanOrEqual(4, $objetivo->turnosEstado());
    }

    public function test_aplicar_estado_con_status_efectivo_burn(): void
    {
        $objetivo = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 80, 'def' => 100],
            tipos: [TipoPokemon::AGUA],
            id: 'd1',
            nombre: 'Objetivo',
        );
        $movimiento = new MovimientoBatalla(
            'Fuego Fatuo',
            0,
            TipoPokemon::FUEGO,
            CategoriaMovimiento::ESPECIAL,
            statusEffect: EstadoPokemon::BURN,
        );

        $this->servicio->aplicarEstado($objetivo, $movimiento);

        $this->assertSame(EstadoPokemon::BURN, $objetivo->estado());
    }

    public function test_aplicar_estado_sin_status_no_cambia(): void
    {
        $objetivo = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 80, 'def' => 100],
            tipos: [TipoPokemon::AGUA],
            id: 'd1',
            nombre: 'Objetivo',
        );
        $movimiento = new MovimientoBatalla('Golpe', 50, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO);

        $this->servicio->aplicarEstado($objetivo, $movimiento);

        $this->assertSame(EstadoPokemon::NONE, $objetivo->estado());
    }

    public function test_aplicar_stat_changes_aplica_self_y_target(): void
    {
        $actor = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::LUCHA],
            id: 'a1',
            nombre: 'Actor',
        );
        $objetivo = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 80, 'def' => 100],
            tipos: [TipoPokemon::AGUA],
            id: 'd1',
            nombre: 'Objetivo',
        );
        $movimiento = new MovimientoBatalla(
            'Danza Espada',
            0,
            TipoPokemon::NORMAL,
            CategoriaMovimiento::ESTADO,
            selfStatChanges: [['stat' => 'attack', 'stages' => 2]],
            targetStatChanges: [['stat' => 'defense', 'stages' => -1]],
        );

        $this->servicio->aplicarStatChanges($actor, $objetivo, $movimiento);

        $this->assertSame(2, $actor->etapas()->obtener('attack'));
        $this->assertSame(-1, $objetivo->etapas()->obtener('defense'));
    }

    public function test_generar_log_movimiento_formato_correcto(): void
    {
        $actor = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::VENENO],
            id: 'a1',
            nombre: 'Atacante',
        );
        $objetivo = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 80, 'def' => 100],
            tipos: [TipoPokemon::AGUA],
            id: 'd1',
            nombre: 'Defensor',
        );
        $movimiento = new MovimientoBatalla('Golpe', 50, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO);

        $log = $this->servicio->generarLogMovimiento(
            $actor,
            $objetivo,
            $movimiento,
            daño: 24.0,
            directPct: 0.1,
            defenderTeamHasVanguard: false,
        );

        $this->assertSame('Atacante usa Golpe → 24 de daño a Defensor [10% directo]', $log);
    }

    public function test_generar_log_movimiento_con_penalizacion_retaguardia(): void
    {
        $actor = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::VENENO],
            id: 'a1',
            nombre: 'Atacante',
        );
        $objetivo = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 80, 'def' => 100],
            tipos: [TipoPokemon::AGUA],
            id: 'd1',
            nombre: 'Retaguardia',
            posicion: Posicion::RETAGUARDIA,
        );
        $movimiento = new MovimientoBatalla('Golpe', 50, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO);

        $log = $this->servicio->generarLogMovimiento(
            $actor,
            $objetivo,
            $movimiento,
            daño: 12.0,
            directPct: 0.0,
            defenderTeamHasVanguard: true,
        );

        $this->assertSame('Atacante usa Golpe → 12 de daño a Retaguardia (-50% retaguardia)', $log);
    }

    public function test_generar_log_movimiento_con_dano_cero_no_muestra_dano(): void
    {
        $actor = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::VENENO],
            id: 'a1',
            nombre: 'Atacante',
        );
        $objetivo = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 80, 'def' => 100],
            tipos: [TipoPokemon::AGUA],
            id: 'd1',
            nombre: 'Defensor',
        );
        $movimiento = new MovimientoBatalla('Golpe', 50, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO);

        $log = $this->servicio->generarLogMovimiento(
            $actor,
            $objetivo,
            $movimiento,
            daño: 0.0,
            directPct: 0.0,
            defenderTeamHasVanguard: false,
        );

        // Con daño=0 no debe mostrar "→ 0 de daño", ni penalizaciones
        $this->assertSame('Atacante usa Golpe', $log);
    }

    public function test_generar_log_movimiento_con_dano_cero_no_muestra_penalizacion_ni_directo(): void
    {
        $actor = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::VENENO],
            id: 'a1',
            nombre: 'Atacante',
        );
        $objetivo = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 80, 'def' => 100],
            tipos: [TipoPokemon::AGUA],
            id: 'd1',
            nombre: 'Retaguardia',
            posicion: Posicion::RETAGUARDIA,
        );
        $movimiento = new MovimientoBatalla('Golpe', 50, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO);

        $log = $this->servicio->generarLogMovimiento(
            $actor,
            $objetivo,
            $movimiento,
            daño: 0.0,
            directPct: 0.5,
            defenderTeamHasVanguard: true,
        );

        // Con daño=0 no debe mostrar penalización ni directo
        $this->assertSame('Atacante usa Golpe', $log);
    }
}
