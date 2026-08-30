<?php

declare(strict_types=1);

namespace Tests\Unit\Battle;

use PHPUnit\Framework\TestCase;
use Src\Battle\Domain\Effects\EfectoPerforacionArmadura;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Tests avanzados de Combatiente: cubre mutantes de obtenerStatEfectivo,
 * puedeActuar con FREEZE/CONFUSION, curarBarreras, obtenerPorcentajeDanioDirecto,
 * agregarVelocidad acumulativa y aArrayVista.
 */
class CombatienteAvanzadoTest extends TestCase
{
    use ConstruyeCombatientes;

    public function test_obtener_stat_efectivo_con_estat_desconocido_devuelve_0(): void
    {
        $c = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::NORMAL],
            id: 'c1',
            nombre: 'Test',
        );

        $this->assertSame(0.0, $c->obtenerStatEfectivo('evasion'));
    }

    public function test_paralysis_reduce_speed_a_la_mitad(): void
    {
        $c = $this->combatiente(
            stats: ['hp' => 100, 'speed' => 100, 'atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::NORMAL],
            id: 'c1',
            nombre: 'Paralizado',
        );
        $c->setEstado(EstadoPokemon::PARALYSIS);

        // speed stat lvl100 = 2*100+5 = 205, con parálisis /2 = 102.5
        $this->assertSame(102.5, $c->obtenerStatEfectivo('speed'));
    }

    public function test_paralysis_no_reduce_attack(): void
    {
        $c = $this->combatiente(
            stats: ['hp' => 100, 'speed' => 100, 'atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::NORMAL],
            id: 'c1',
            nombre: 'Paralizado',
        );
        $c->setEstado(EstadoPokemon::PARALYSIS);

        // attack stat lvl100 = 2*100+5 = 205, sin reducción
        $this->assertSame(205.0, $c->obtenerStatEfectivo('attack'));
    }

    public function test_obtener_stat_efectivo_con_stage_no_cero_aplica_multiplicador(): void
    {
        $c = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::NORMAL],
            id: 'c1',
            nombre: 'Buff',
        );
        $c->aplicarCambioEtapa('attack', 2);

        // attack stat = 205 * 2.0 = 410
        $this->assertSame(410.0, $c->obtenerStatEfectivo('attack'));
    }

    public function test_freeze_puede_impedir_actuar(): void
    {
        $c = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::HIELO],
            id: 'c1',
            nombre: 'Congelado',
        );
        $c->setEstado(EstadoPokemon::FREEZE);

        mt_srand(42); // seed que no descongela (20% chance)
        $result = $c->puedeActuar();

        $this->assertFalse($result['canAct']);
        $this->assertSame('está congelado', $result['reason']);
    }

    public function test_sleep_turnos_decrecen_y_despierte(): void
    {
        $c = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::PSIQUICO],
            id: 'c1',
            nombre: 'Dormido',
        );
        $c->setEstado(EstadoPokemon::SLEEP);
        $c->setTurnosEstado(1);

        $result = $c->puedeActuar();
        $this->assertFalse($result['canAct']);
        $this->assertSame('está dormido', $result['reason']);

        // Siguiente llamada: turnos 0 → despierta
        $result2 = $c->puedeActuar();
        $this->assertTrue($result2['canAct']);
        $this->assertSame('despertó', $result2['reason']);
        $this->assertSame(EstadoPokemon::NONE, $c->estado());
    }

    public function test_curar_barreras_no_supera_maximo(): void
    {
        $c = $this->combatiente(
            stats: ['hp' => 100, 'def' => 100, 'spDef' => 100],
            tipos: [TipoPokemon::NORMAL],
            id: 'c1',
            nombre: 'Defensor',
        );

        $maxDef = $c->pokemon()->battleStats()->defenseHp;
        $maxSpDef = $c->pokemon()->battleStats()->spDefenseHp;

        // Vaciar barreras completamente
        $c->setDefensaHpActual(0);
        $c->setDefensaEspHpActual(0);

        $c->curarBarreras(100.0); // 100% → debe restaurar al máximo

        $this->assertSame($maxDef, $c->defensaHpActual());
        $this->assertSame($maxSpDef, $c->defensaEspHpActual());
    }

    public function test_agregar_velocidad_acumula(): void
    {
        $c = $this->combatiente(
            stats: ['hp' => 100, 'speed' => 100, 'atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::NORMAL],
            id: 'c1',
            nombre: 'Rápido',
        );

        $c->agregarVelocidad();
        $primera = $c->velocidadAcumulada(); // 205

        $c->agregarVelocidad();
        $this->assertSame($primera * 2, $c->velocidadAcumulada());
    }

    public function test_obtener_porcentaje_danio_directo_capado_a_1_0(): void
    {
        $c = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::NORMAL],
            id: 'c1',
            nombre: 'Perforador',
        );

        // Dos efectos de perforación → 0.10 + 0.10 = 0.20, cap 1.0
        $c->effects()->add(new EfectoPerforacionArmadura('armor_pierce', 0.10));
        $c->effects()->add(new EfectoPerforacionArmadura('armor_pierce_2', 0.95));

        // 0.10 + 0.95 = 1.05 → min(1.05, 1.0) = 1.0
        $this->assertSame(1.0, $c->obtenerPorcentajeDanioDirecto());
    }

    public function test_recibir_dano_excedente_cero_no_cambia_hp(): void
    {
        $c = $this->combatiente(
            stats: ['hp' => 100, 'def' => 100, 'spDef' => 100],
            tipos: [TipoPokemon::NORMAL],
            id: 'c1',
            nombre: 'Defensor',
        );

        $maxHp = $c->hpActual();
        $barInicial = $c->defensaHpActual();

        // Daño exactamente igual a la barrera → excedente 0
        $c->recibirDaño($barInicial, false);

        $this->assertSame($maxHp, $c->hpActual());
        $this->assertSame(0.0, $c->defensaHpActual());
    }

    public function test_aplicar_dano_status_no_aplica_si_muerto(): void
    {
        $c = $this->combatiente(
            stats: ['hp' => 160, 'atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::FUEGO],
            id: 'c1',
            nombre: 'Quemado',
        );
        $c->setEstado(EstadoPokemon::BURN);
        $c->setHpActual(0);

        $daño = $c->aplicarDañoStatus();

        $this->assertSame(0.0, $daño);
        $this->assertSame(0.0, $c->hpActual());
    }

    public function test_aarray_vista_contiene_campos_esenciales(): void
    {
        $c = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::NORMAL],
            id: 'c1',
            nombre: 'Test',
            item: 'life_orb',
        );

        $vista = $c->aArrayVista(0);

        $this->assertSame('c1', $vista['refId']);
        $this->assertSame('Test', $vista['nombre']);
        $this->assertTrue($vista['alive']);
        $this->assertSame('life_orb', $vista['item']);
        $this->assertSame('vanguardia', $vista['posicion']);
        $this->assertArrayHasKey('hp', $vista);
        $this->assertArrayHasKey('maxHp', $vista);
        $this->assertArrayHasKey('defHp', $vista);
        $this->assertArrayHasKey('spDefHp', $vista);
        $this->assertArrayHasKey('accumulatedSpeed', $vista);
    }
}
