<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use PHPUnit\Framework\TestCase;
use Src\Exploraciones\Domain\CalculadorPeligro;

class CalculadorPeligroTest extends TestCase
{
    public function test_peligro_zona_escala_normal_suma_peligro_y_nivel(): void
    {
        // D0/RF-01: peligroZona = (peligro + nivel) × escala.
        $this->assertSame(10, CalculadorPeligro::peligroZona(1, 1));
        $this->assertSame(25, CalculadorPeligro::peligroZona(2, 3));
        $this->assertSame(40, CalculadorPeligro::peligroZona(5, 3));
    }

    public function test_peligro_zona_escala_alta_multiplica_por_diez(): void
    {
        $this->assertSame(20, CalculadorPeligro::peligroZona(1, 1, CalculadorPeligro::ESCALA_ALTA));
        $this->assertSame(80, CalculadorPeligro::peligroZona(5, 3, CalculadorPeligro::ESCALA_ALTA));
    }

    public function test_peligro_se_clampa_a_1_5(): void
    {
        // Valores fuera de rango se normalizan al dominio 1–5.
        $this->assertSame(10, CalculadorPeligro::peligroZona(0, 1));
        $this->assertSame(10, CalculadorPeligro::peligroZona(-4, 1));
        $this->assertSame(40, CalculadorPeligro::peligroZona(9, 3));
    }

    public function test_nivel_minimo_uno(): void
    {
        $this->assertSame(10, CalculadorPeligro::peligroZona(1, 0));
        $this->assertSame(10, CalculadorPeligro::peligroZona(1, -2));
    }
}
