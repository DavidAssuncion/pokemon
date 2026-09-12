<?php

declare(strict_types=1);

namespace Tests\Unit\Pokemon;

use PHPUnit\Framework\TestCase;
use Src\Pokemon\Domain\Stats\BattleStats;
use Src\Pokemon\Domain\Stats\CalculadorCP;
use Src\Pokemon\Domain\Stats\StatsValue;

class CalculadorCPTest extends TestCase
{
    /**
     * Stats base: hp=60, resto=100. Nivel 50 sin EVs:
     *   hp = floor(120*0.5 + 60) = 120
     *   resto = floor(200*0.5 + 5) = 105
     *   suma = 120 + 105*5 = 645
     *   CP = floor(645 * 50 * 6 / 100 + 0) = floor(1935) = 1935
     */
    public function test_calcula_cp_con_ev_cero(): void
    {
        $stats = new BattleStats(
            stats: new StatsValue(hp: 60, attack: 100, defense: 100, spAtk: 100, spDef: 100, speed: 100),
            evs: new StatsValue(0, 0, 0, 0, 0, 0),
            nivel: 50,
        );

        $cp = CalculadorCP::calcular($stats, 0);

        $this->assertSame(1935, $cp);
    }

    /**
     * Con EV=252 a nivel 50:
     *   termino EV = 252 * ((50/4)/100 + 2) = 252 * 2.125 = 535.5
     *   CP = floor(1935 + 535.5) = floor(2470.5) = 2470
     */
    public function test_calcula_cp_con_ev_mayor_que_cero(): void
    {
        $stats = new BattleStats(
            stats: new StatsValue(hp: 60, attack: 100, defense: 100, spAtk: 100, spDef: 100, speed: 100),
            evs: new StatsValue(0, 0, 0, 0, 0, 0),
            nivel: 50,
        );

        $cp = CalculadorCP::calcular($stats, 252);

        $this->assertSame(2470, $cp);
    }

    /**
     * El índice de EV admite EVs distribuidos por stat (EvsRangoEntrenador):
     * se pasa la suma de EVs del rival. El valor nunca es negativo.
     */
    public function test_calcula_cp_con_evs_distribuidos_y_nivel_100(): void
    {
        $stats = new BattleStats(
            stats: new StatsValue(hp: 60, attack: 100, defense: 100, spAtk: 100, spDef: 100, speed: 100),
            evs: new StatsValue(64, 64, 64, 64, 64, 64),
            nivel: 100,
        );

        // BattleStats ya incorpora el EV por stat a nivel 100:
        //   hp  = floor((120 + 16) * 1 + 110) = 246
        //   resto = floor((200 + 16) * 1 + 5) = 221
        //   suma = 246 + 221*5 = 1351
        //   base = 1351 * 100 * 6 / 100 = 8106
        // EV index = 384 * ((100/4)/100 + 2) = 384 * 2.25 = 864
        // CP = floor(8106 + 864) = 8970
        $cp = CalculadorCP::calcular($stats, 384);

        $this->assertSame(8970, $cp);
    }
}
