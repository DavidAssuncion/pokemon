<?php

declare(strict_types=1);

namespace Tests\Unit\Reclutamiento;

use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\ProbabilidadCaptura;

/**
 * Regla unificada de captura (cap-25) en Shared/Domain. Se mantiene este
 * archivo en Reclutamiento para no romper la convención de ubicación del test
 * histórico, pero cubre la clase de dominio compartida.
 */
class ProbabilidadCapturaTest extends TestCase
{
    public function test_tasa_255_se_recorta_al_cap_25(): void
    {
        $this->assertEqualsWithDelta(25 / 255, ProbabilidadCaptura::probabilidad(255), 0.0000001);
    }

    public function test_probabilidad_mayor_que_cap_se_recorta_a_25_entre_255(): void
    {
        $this->assertEqualsWithDelta(25 / 255, ProbabilidadCaptura::probabilidad(300), 0.0000001);
        $this->assertEqualsWithDelta(25 / 255, ProbabilidadCaptura::probabilidad(190), 0.0000001);
    }

    public function test_probabilidad_45_es_25_entre_255(): void
    {
        $this->assertEqualsWithDelta(25 / 255, ProbabilidadCaptura::probabilidad(45), 0.0000001);
    }

    public function test_probabilidad_30_se_recorta_al_cap_25(): void
    {
        $this->assertEqualsWithDelta(25 / 255, ProbabilidadCaptura::probabilidad(30), 0.0000001);
    }

    public function test_probabilidad_3_es_3_entre_255(): void
    {
        $this->assertEqualsWithDelta(3 / 255, ProbabilidadCaptura::probabilidad(3), 0.0000001);
    }

    public function test_capture_rate_cero_usa_la_tasa_base_por_defecto_cap_25(): void
    {
        $this->assertEqualsWithDelta(25 / 255, ProbabilidadCaptura::probabilidad(0), 0.0000001);
    }

    public function test_capture_rate_negativo_usa_la_tasa_base_por_defecto_cap_25(): void
    {
        $this->assertEqualsWithDelta(25 / 255, ProbabilidadCaptura::probabilidad(-10), 0.0000001);
    }

    public function test_intentar_es_exitoso_en_el_borde_con_tasa_base_25(): void
    {
        $this->assertTrue(ProbabilidadCaptura::intentar(45, fn (): float => 0.09));
        $this->assertTrue(ProbabilidadCaptura::intentar(45, fn (): float => 25 / 255));
    }

    public function test_intentar_falla_cuando_el_aleatorio_supera_la_probabilidad(): void
    {
        $this->assertFalse(ProbabilidadCaptura::intentar(45, fn (): float => 0.1));
        $this->assertFalse(ProbabilidadCaptura::intentar(3, fn (): float => 0.02));
    }

    public function test_intentar_255_usa_el_cap_9_8_por_ciento(): void
    {
        // 255 → cap 25 → chance 25/255 ≈ 0.098. Un aleatorio 0.99 NUNCA captura.
        $this->assertFalse(ProbabilidadCaptura::intentar(255, fn (): float => 0.99));
        $this->assertTrue(ProbabilidadCaptura::intentar(255, fn (): float => 0.0));
    }

    public function test_intentar_con_capture_rate_cero_usa_la_tasa_base_cap_25(): void
    {
        $this->assertFalse(ProbabilidadCaptura::intentar(0, fn (): float => 0.1));
        $this->assertTrue(ProbabilidadCaptura::intentar(0, fn (): float => 0.09));
    }
}
