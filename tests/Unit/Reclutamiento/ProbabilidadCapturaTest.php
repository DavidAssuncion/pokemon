<?php

declare(strict_types=1);

namespace Tests\Unit\Reclutamiento;

use PHPUnit\Framework\TestCase;
use Src\Reclutamiento\Domain\ProbabilidadCaptura;

class ProbabilidadCapturaTest extends TestCase
{
    public function test_probabilidad_255_es_siempre_uno(): void
    {
        $this->assertSame(1.0, ProbabilidadCaptura::probabilidad(255));
    }

    public function test_probabilidad_mayor_que_255_se_recorta_a_uno(): void
    {
        $this->assertSame(1.0, ProbabilidadCaptura::probabilidad(300));
    }

    public function test_probabilidad_45_es_45_entre_255(): void
    {
        $this->assertEqualsWithDelta(45 / 255, ProbabilidadCaptura::probabilidad(45), 0.0000001);
    }

    public function test_capture_rate_cero_usa_la_tasa_base_por_defecto(): void
    {
        $this->assertEqualsWithDelta(45 / 255, ProbabilidadCaptura::probabilidad(0), 0.0000001);
    }

    public function test_capture_rate_negativo_usa_la_tasa_base_por_defecto(): void
    {
        $this->assertEqualsWithDelta(45 / 255, ProbabilidadCaptura::probabilidad(-10), 0.0000001);
    }

    public function test_intentar_es_exitoso_cuando_el_aleatorio_es_menor_o_igual_a_la_probabilidad(): void
    {
        $this->assertTrue(ProbabilidadCaptura::intentar(255, fn (): float => 0.999));
        $this->assertTrue(ProbabilidadCaptura::intentar(45, fn (): float => 0.17));
        $this->assertTrue(ProbabilidadCaptura::intentar(45, fn (): float => 45 / 255));
    }

    public function test_intentar_falla_cuando_el_aleatorio_supera_la_probabilidad(): void
    {
        $this->assertFalse(ProbabilidadCaptura::intentar(45, fn (): float => 0.18));
        $this->assertFalse(ProbabilidadCaptura::intentar(1, fn (): float => 0.004));
    }

    public function test_intentar_con_capture_rate_cero_usa_la_probabilidad_por_defecto(): void
    {
        $this->assertFalse(ProbabilidadCaptura::intentar(0, fn (): float => 0.18));
        $this->assertTrue(ProbabilidadCaptura::intentar(0, fn (): float => 0.0));
    }
}
