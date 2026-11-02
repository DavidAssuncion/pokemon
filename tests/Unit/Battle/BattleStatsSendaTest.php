<?php

declare(strict_types=1);

namespace Tests\Unit\Battle;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Pokemon\Domain\Stats\BattleStats;
use Src\Pokemon\Domain\Stats\DatosStats;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Shared\Domain\PerfilIvsEvs;
use Src\Shared\Domain\PoliticaIvsEvs;

/**
 * Stats efectivos a L=25 con los medios del hábitat nivel 1:
 * hp 49,9 · atk 54,0 · def 51,9 · spAtk 47,7 · spDef 48,7 · speed 50,2.
 *
 * El cálculo efectivo usa los medios exactos (StatsValue admite float); el
 * ranking top-N de PoliticaIvsEvs exige DatosStats enteros y usa esos mismos
 * medios redondeados (50/54/52/48/49/50), que conservan el orden por base.
 */
class BattleStatsSendaTest extends TestCase
{
    private function statsBase(): StatsValue
    {
        return new StatsValue(
            hp: 49.9,
            attack: 54.0,
            defense: 51.9,
            spAtk: 47.7,
            spDef: 48.7,
            speed: 50.2,
        );
    }

    private function statsBaseRanking(): DatosStats
    {
        return new DatosStats(hp: 50, atk: 54, def: 52, spAtk: 48, spDef: 49, speed: 50);
    }

    private function batallaCon(PerfilIvsEvs $perfil, int $nivel = 25): BattleStats
    {
        $base = $this->statsBaseRanking();

        return new BattleStats(
            stats: $this->statsBase(),
            evs: PoliticaIvsEvs::evs($perfil, $base),
            nivel: $nivel,
            ivs: PoliticaIvsEvs::ivs($perfil, $base),
        );
    }

    #[Test]
    public function test_perfil_nulo_da_los_stats_minimos(): void
    {
        $batalla = $this->batallaCon(PerfilIvsEvs::Nulo);

        $this->assertSame(59.0, $batalla->hp);
        $this->assertSame(32.0, $batalla->attack);
        $this->assertSame(30.0, $batalla->defense);
    }

    #[Test]
    public function test_perfil_top_uno_duplica_el_ataque_sin_tocar_hp_ni_defensa(): void
    {
        $batalla = $this->batallaCon(PerfilIvsEvs::TopUno);

        $this->assertSame(59.0, $batalla->hp);
        $this->assertSame(66.0, $batalla->attack);
        $this->assertSame(30.0, $batalla->defense);
    }

    #[Test]
    public function test_perfil_top_dos_sube_hp_y_defensa(): void
    {
        $batalla = $this->batallaCon(PerfilIvsEvs::TopDos);

        $this->assertSame(77.0, $batalla->hp);
        $this->assertSame(66.0, $batalla->attack);
        $this->assertSame(65.0, $batalla->defense);
    }

    #[Test]
    public function test_perfil_completo_solo_anade_hp_sobre_top_dos(): void
    {
        $batalla = $this->batallaCon(PerfilIvsEvs::Completo);

        $this->assertSame(94.0, $batalla->hp);
        $this->assertSame(66.0, $batalla->attack);
        $this->assertSame(65.0, $batalla->defense);
    }
}
