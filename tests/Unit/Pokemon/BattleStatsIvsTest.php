<?php

declare(strict_types=1);

namespace Tests\Unit\Pokemon;

use PHPUnit\Framework\TestCase;
use Src\Pokemon\Domain\Stats\BattleStats;
use Src\Pokemon\Domain\Stats\StatsValue;

class BattleStatsIvsTest extends TestCase
{
    public function test_cada_punto_de_iv_suma_cinco_al_stat_a_nivel_cien(): void
    {
        $sinIvs = new BattleStats(
            stats: new StatsValue(hp: 60, attack: 100, defense: 100, spAtk: 100, spDef: 100, speed: 100),
            evs: StatsValue::ceros(),
            nivel: 100,
        );

        $conIvs = new BattleStats(
            stats: new StatsValue(hp: 60, attack: 100, defense: 100, spAtk: 100, spDef: 100, speed: 100),
            evs: StatsValue::ceros(),
            nivel: 100,
            ivs: new StatsValue(hp: 15, attack: 15, defense: 15, spAtk: 15, spDef: 15, speed: 15),
        );

        // attack = floor((2*100 + 15*5) * 100/100 + 5) = 280
        $this->assertSame(205.0, $sinIvs->attack);
        $this->assertSame(280.0, $conIvs->attack);
        // hp = floor((2*60 + 15*5) * 100/100 + 100 + 10) = 305
        $this->assertSame(230.0, $sinIvs->hp);
        $this->assertSame(305.0, $conIvs->hp);
        // Cada punto de IV suma exactamente ×5 al stat a nivel 100 (decisión de producto).
        $this->assertSame(15.0 * BattleStats::IV, $conIvs->attack - $sinIvs->attack);
    }

    public function test_ev_divide_entre_cuatro_por_stat(): void
    {
        $sinEvs = new BattleStats(
            stats: new StatsValue(hp: 100, attack: 100, defense: 100, spAtk: 100, spDef: 100, speed: 100),
            evs: StatsValue::ceros(),
            nivel: 100,
        );

        $conEvs = new BattleStats(
            stats: new StatsValue(hp: 100, attack: 100, defense: 100, spAtk: 100, spDef: 100, speed: 100),
            evs: new StatsValue(hp: 0, attack: 252, defense: 0, spAtk: 0, spDef: 0, speed: 0),
            nivel: 100,
        );

        // attack = floor((2*100 + floor(252/4)) * 100/100 + 5) = 268
        $this->assertSame(205.0, $sinEvs->attack);
        $this->assertSame(268.0, $conEvs->attack);
    }
}
