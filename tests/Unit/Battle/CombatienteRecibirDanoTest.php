<?php

declare(strict_types=1);

namespace Tests\Unit\Battle;

use PHPUnit\Framework\TestCase;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Combatiente::recibirDaño: barreras duales, daño directo (directPct) y clamp HP.
 */
class CombatienteRecibirDanoTest extends TestCase
{
    use ConstruyeCombatientes;

    public function test_dano_absorbido_por_barrera_no_afecta_hp(): void
    {
        $defensor = $this->combatiente(
            stats: ['hp' => 100, 'def' => 100, 'spDef' => 100],
            tipos: [TipoPokemon::NORMAL],
            id: 'd1',
            nombre: 'Defensor',
        );

        $maxHp = $defensor->hpActual();
        $barInicial = $defensor->defensaHpActual();

        $defensor->recibirDaño(50.0, false);

        $this->assertSame($maxHp, $defensor->hpActual());
        $this->assertSame($barInicial - 50, $defensor->defensaHpActual());
    }

    public function test_dano_excede_barrera_afecta_hp(): void
    {
        $defensor = $this->combatiente(
            stats: ['hp' => 100, 'def' => 100, 'spDef' => 100],
            tipos: [TipoPokemon::NORMAL],
            id: 'd1',
            nombre: 'Defensor',
        );

        $maxHp = $defensor->hpActual();
        $barInicial = $defensor->defensaHpActual();

        // Daño 500 > barrera (~310 con def=100 lvl100) → excedente a HP
        $defensor->recibirDaño(500.0, false);

        $this->assertSame(0.0, $defensor->defensaHpActual());
        $this->assertSame($maxHp - (500 - $barInicial), $defensor->hpActual());
    }

    public function test_direct_pct_penetra_barrera(): void
    {
        $defensor = $this->combatiente(
            stats: ['hp' => 100, 'def' => 100, 'spDef' => 100],
            tipos: [TipoPokemon::NORMAL],
            id: 'd1',
            nombre: 'Defensor',
        );

        $maxHp = $defensor->hpActual();
        $barInicial = $defensor->defensaHpActual();

        // directPct 0.5 → 50% directo a HP, 50% a barrera
        $defensor->recibirDaño(100.0, false, 0.5);

        $this->assertSame($maxHp - 50, $defensor->hpActual());
        $this->assertSame($barInicial - 50, $defensor->defensaHpActual());
    }

    public function test_direct_pct_1_0_todo_a_hp(): void
    {
        $defensor = $this->combatiente(
            stats: ['hp' => 100, 'def' => 100, 'spDef' => 100],
            tipos: [TipoPokemon::NORMAL],
            id: 'd1',
            nombre: 'Defensor',
        );

        $maxHp = $defensor->hpActual();
        $barInicial = $defensor->defensaHpActual();

        $defensor->recibirDaño(100.0, false, 1.0);

        $this->assertSame($maxHp - 100, $defensor->hpActual());
        $this->assertSame($barInicial, $defensor->defensaHpActual());
    }

    public function test_dano_especial_usa_barrera_especial(): void
    {
        $defensor = $this->combatiente(
            stats: ['hp' => 100, 'def' => 100, 'spDef' => 100],
            tipos: [TipoPokemon::NORMAL],
            id: 'd1',
            nombre: 'Defensor',
        );

        $barFisica = $defensor->defensaHpActual();
        $barEsp = $defensor->defensaEspHpActual();
        $maxHp = $defensor->hpActual();

        $defensor->recibirDaño(100.0, true);

        // La barrera física queda intacta; la especial absorbe
        $this->assertSame($barFisica, $defensor->defensaHpActual());
        $this->assertSame($barEsp - 100, $defensor->defensaEspHpActual());
        $this->assertSame($maxHp, $defensor->hpActual());
    }

    public function test_hp_nunca_negativo(): void
    {
        $defensor = $this->combatiente(
            stats: ['hp' => 100, 'def' => 100, 'spDef' => 100],
            tipos: [TipoPokemon::NORMAL],
            id: 'd1',
            nombre: 'Defensor',
        );

        // Daño directo masivo → HP debe quedar clampado a 0
        $defensor->recibirDaño(10000.0, false, 1.0);

        $this->assertSame(0.0, $defensor->hpActual());
        $this->assertFalse($defensor->estaVivo());
    }
}
