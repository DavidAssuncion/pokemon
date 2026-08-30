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
}
