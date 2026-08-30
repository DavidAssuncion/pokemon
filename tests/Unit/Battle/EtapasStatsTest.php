<?php

declare(strict_types=1);

namespace Tests\Unit\Battle;

use PHPUnit\Framework\TestCase;
use Src\Battle\Domain\ValueObjects\EtapasStats;

/**
 * EtapasStats: clamp -6..+6 y multiplicadores correctos.
 */
class EtapasStatsTest extends TestCase
{
    public function test_aplicar_cambio_clampea_a_superior(): void
    {
        $etapas = (new EtapasStats(['attack' => 5]))->aplicarCambio('attack', 5);

        $this->assertSame(6, $etapas->obtener('attack'));
    }

    public function test_aplicar_cambio_clampea_a_inferior(): void
    {
        $etapas = (new EtapasStats(['attack' => -5]))->aplicarCambio('attack', -5);

        $this->assertSame(-6, $etapas->obtener('attack'));
    }

    public function test_multiplicador_positivo(): void
    {
        $etapas = new EtapasStats(['attack' => 2]);

        $this->assertSame(2.0, $etapas->obtenerMultiplicador('attack'));
    }

    public function test_multiplicador_negativo(): void
    {
        $etapas = new EtapasStats(['attack' => -2]);

        $this->assertSame(0.5, $etapas->obtenerMultiplicador('attack'));
    }

    public function test_aplicar_cambio_es_inmutable(): void
    {
        $original = new EtapasStats(['attack' => 1]);
        $nueva = $original->aplicarCambio('attack', 1);

        $this->assertSame(1, $original->obtener('attack'));
        $this->assertSame(2, $nueva->obtener('attack'));
    }

    public function test_constructor_lanza_excepcion_si_valor_mayor_que_6(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new EtapasStats(['attack' => 7]);
    }

    public function test_constructor_lanza_excepcion_si_valor_menor_que_menos_6(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new EtapasStats(['attack' => -7]);
    }

    public function test_obtener_no_neutras_solo_devuelve_distintas_de_cero(): void
    {
        $etapas = new EtapasStats([
            'attack' => 2,
            'defense' => 0,
            'spAtk' => -1,
            'speed' => 0,
        ]);

        $noNeutras = $etapas->obtenerNoNeutras();

        $this->assertArrayHasKey('attack', $noNeutras);
        $this->assertArrayHasKey('spAtk', $noNeutras);
        $this->assertArrayNotHasKey('defense', $noNeutras);
        $this->assertArrayNotHasKey('speed', $noNeutras);
        $this->assertCount(2, $noNeutras);
    }

    public function test_multiplicador_extremo_superior_6_es_4(): void
    {
        $etapas = new EtapasStats(['attack' => 6]);
        $this->assertSame(4.0, $etapas->obtenerMultiplicador('attack'));
    }

    public function test_multiplicador_extremo_inferior_menos_6_es_0_25(): void
    {
        $etapas = new EtapasStats(['attack' => -6]);
        $this->assertSame(0.25, $etapas->obtenerMultiplicador('attack'));
    }
}
