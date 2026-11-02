<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\ProbabilidadCaptura;

class ProbabilidadCapturaTest extends TestCase
{
    #[Test]
    public function test_factor_0_5_reduce_la_probabilidad_de_captura(): void
    {
        // capture_rate 255 → min(255, 25) / 255 ≈ 0.098. Con el mismo roll 0.05:
        // sin factor captura; con factor 0.5 → ≈ 0.049 y ya no captura.
        $aleatorio = fn (): float => 0.05;

        $this->assertTrue(ProbabilidadCaptura::intentarConFactor(255, 1.0, $aleatorio));
        $this->assertFalse(ProbabilidadCaptura::intentarConFactor(255, 0.5, $aleatorio));
    }

    #[Test]
    public function test_probabilidad_con_factor_se_aplica_sobre_el_cap(): void
    {
        // El factor se aplica a la probabilidad final (no al capture_rate), por
        // lo que el cap-25 no lo anula: 25/255 × 0.5 = 12.5/255.
        $this->assertEqualsWithDelta(
            12.5 / 255,
            ProbabilidadCaptura::probabilidadConFactor(255, 0.5),
            0.0000001,
        );
    }

    #[Test]
    public function test_ruta_y_exploracion_comparten_la_misma_formula_y_cap(): void
    {
        // Ruta usa ProbabilidadCaptura::intentar() y exploración
        // ProbabilidadCaptura::intentarConFactor(..., 1.0). Con el mismo roll
        // deben decidir igual y compartir el cap-25.
        $tasas = [0, 3, 25, 45, 166, 255, 300];
        $rolls = [0.0, 0.05, 0.09, 0.098, 0.099, 0.5, 0.99];

        foreach ($tasas as $tasa) {
            $tasaEfectiva = $tasa > 0 ? $tasa : ProbabilidadCaptura::TASA_BASE;
            $cap = min($tasaEfectiva, ProbabilidadCaptura::CAP) / ProbabilidadCaptura::MAXIMO;

            $this->assertEqualsWithDelta(
                $cap,
                ProbabilidadCaptura::probabilidad($tasa),
                0.0000001,
            );

            foreach ($rolls as $roll) {
                $aleatorio = fn (): float => $roll;

                $this->assertSame(
                    ProbabilidadCaptura::intentar($tasa, $aleatorio),
                    ProbabilidadCaptura::intentarConFactor($tasa, 1.0, $aleatorio),
                    "Ruta y exploración difieren para tasa {$tasa} y roll {$roll}",
                );
            }
        }
    }

    #[Test]
    public function cap_senda_limita_la_probabilidad_al_treinta_por_ciento(): void
    {
        $this->assertSame(0.30, ProbabilidadCaptura::CAP_SENDA);

        $this->assertSame(
            ProbabilidadCaptura::CAP_SENDA,
            ProbabilidadCaptura::probabilidadConFactor(255, 100.0),
        );
        $this->assertLessThanOrEqual(
            ProbabilidadCaptura::CAP_SENDA,
            ProbabilidadCaptura::probabilidadConFactor(255, 1.5 * 1.2),
        );
    }

    #[Test]
    public function intentar_con_factor_nunca_supera_el_cap_senda(): void
    {
        $this->assertFalse(
            ProbabilidadCaptura::intentarConFactor(255, 100.0, fn (): float => 0.31),
        );
        $this->assertTrue(
            ProbabilidadCaptura::intentarConFactor(255, 100.0, fn (): float => 0.30),
        );
    }
}
