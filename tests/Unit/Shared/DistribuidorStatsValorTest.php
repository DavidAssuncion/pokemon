<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Pokemon\Domain\Stats\DatosStats;
use Src\Shared\Domain\DistribuidorStatsValor;

class DistribuidorStatsValorTest extends TestCase
{
    private static function base(): DatosStats
    {
        return new DatosStats(hp: 45, atk: 60, def: 40, spAtk: 50, spDef: 45, speed: 70);
    }

    #[Test]
    public function test_distribuir_conserva_el_reparto_top_dos_historico(): void
    {
        $resultado = DistribuidorStatsValor::distribuir(15, 8, self::base())->toArray();

        // Ranking de base: speed(70), atk(60), spAtk(50), hp(45), spDef(45), def(40).
        $this->assertSame(
            ['hp' => 8, 'atk' => 15, 'def' => 8, 'spAtk' => 8, 'spDef' => 8, 'speed' => 15],
            $resultado,
        );
    }

    #[Test]
    public function test_distribuir_delega_en_el_reparto_top_dos(): void
    {
        $this->assertSame(
            DistribuidorStatsValor::distribuirPorRanking(2, 15, 8, self::base())->toArray(),
            DistribuidorStatsValor::distribuir(15, 8, self::base())->toArray(),
        );
    }

    #[Test]
    public function test_top_cero_asigna_el_resto_a_los_seis_stats(): void
    {
        $this->assertSame(
            ['hp' => 8, 'atk' => 8, 'def' => 8, 'spAtk' => 8, 'spDef' => 8, 'speed' => 8],
            DistribuidorStatsValor::distribuirPorRanking(0, 15, 8, self::base())->toArray(),
        );
    }

    #[Test]
    public function test_top_uno_asigna_el_principal_solo_al_mejor_stat_base(): void
    {
        $this->assertSame(
            ['hp' => 8, 'atk' => 8, 'def' => 8, 'spAtk' => 8, 'spDef' => 8, 'speed' => 15],
            DistribuidorStatsValor::distribuirPorRanking(1, 15, 8, self::base())->toArray(),
        );
    }

    #[Test]
    public function test_top_dos_asigna_el_principal_a_los_dos_mejores_stats_base(): void
    {
        $this->assertSame(
            ['hp' => 8, 'atk' => 15, 'def' => 8, 'spAtk' => 8, 'spDef' => 8, 'speed' => 15],
            DistribuidorStatsValor::distribuirPorRanking(2, 15, 8, self::base())->toArray(),
        );
    }

    #[Test]
    public function test_top_seis_asigna_el_principal_a_los_seis_stats(): void
    {
        $this->assertSame(
            ['hp' => 15, 'atk' => 15, 'def' => 15, 'spAtk' => 15, 'spDef' => 15, 'speed' => 15],
            DistribuidorStatsValor::distribuirPorRanking(6, 15, 8, self::base())->toArray(),
        );
    }

    #[Test]
    public function test_el_numero_de_principales_se_limita_entre_cero_y_seis(): void
    {
        $todosResto = ['hp' => 8, 'atk' => 8, 'def' => 8, 'spAtk' => 8, 'spDef' => 8, 'speed' => 8];
        $todosPrincipal = ['hp' => 15, 'atk' => 15, 'def' => 15, 'spAtk' => 15, 'spDef' => 15, 'speed' => 15];

        $this->assertSame($todosResto, DistribuidorStatsValor::distribuirPorRanking(-3, 15, 8, self::base())->toArray());
        $this->assertSame($todosPrincipal, DistribuidorStatsValor::distribuirPorRanking(99, 15, 8, self::base())->toArray());
    }

    #[Test]
    public function test_los_empates_por_base_se_resuelven_con_el_orden_fijo(): void
    {
        $base = new DatosStats(hp: 50, atk: 50, def: 50, spAtk: 50, spDef: 50, speed: 50);

        $this->assertSame(
            ['hp' => 15, 'atk' => 15, 'def' => 15, 'spAtk' => 8, 'spDef' => 8, 'speed' => 8],
            DistribuidorStatsValor::distribuirPorRanking(3, 15, 8, $base)->toArray(),
        );
    }

    #[Test]
    public function test_el_empate_hp_spdef_lo_gana_hp_por_el_orden_fijo(): void
    {
        // speed(70) y atk(60) ya ocupan el top-2; el cuarto puesto empata hp/spDef a 45.
        $this->assertSame(
            ['hp' => 15, 'atk' => 15, 'def' => 8, 'spAtk' => 15, 'spDef' => 8, 'speed' => 15],
            DistribuidorStatsValor::distribuirPorRanking(4, 15, 8, self::base())->toArray(),
        );
    }
}
