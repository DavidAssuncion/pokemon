<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\NivelHelper;

class NivelHelperTest extends TestCase
{
    public function test_nivel_uno_con_cero_o_poca_experiencia(): void
    {
        $this->assertSame(1, NivelHelper::nivelDesdeExperiencia(0));
        $this->assertSame(1, NivelHelper::nivelDesdeExperiencia(9));
        $this->assertSame(1, NivelHelper::nivelDesdeExperiencia(79)); // umbral de 2 = 10 × 2³ = 80
    }

    public function test_nivel_dos_en_umbral_80(): void
    {
        $this->assertSame(2, NivelHelper::nivelDesdeExperiencia(80));
        $this->assertSame(2, NivelHelper::nivelDesdeExperiencia(269)); // umbral de 3 = 10 × 3³ = 270
    }

    public function test_umbrales_curva_media_x10(): void
    {
        // umbral(nivel) = 10 × nivel³ → nivel 4 en 640, nivel 5 en 1.250
        $this->assertSame(3, NivelHelper::nivelDesdeExperiencia(270));
        $this->assertSame(3, NivelHelper::nivelDesdeExperiencia(639));
        $this->assertSame(4, NivelHelper::nivelDesdeExperiencia(640));
        $this->assertSame(4, NivelHelper::nivelDesdeExperiencia(1_249));
        $this->assertSame(5, NivelHelper::nivelDesdeExperiencia(1_250));
    }

    public function test_precision_flotante_con_potencia_exacta(): void
    {
        // base = 125 → 125 ** (1/3) = 4.999... → la corrección debe devolver nivel 5
        $this->assertSame(5, NivelHelper::nivelDesdeExperiencia(1_250));
        // base = 1.000.000 → 10.000.000 de exp = nivel 100 exacto
        $this->assertSame(100, NivelHelper::nivelDesdeExperiencia(10_000_000));
    }

    public function test_nivel_100_exactamente_en_10_millones(): void
    {
        $this->assertSame(99, NivelHelper::nivelDesdeExperiencia(9_999_999));
        $this->assertSame(100, NivelHelper::nivelDesdeExperiencia(10_000_000));
    }

    public function test_sin_tope_de_nivel(): void
    {
        // 10.000.001 aún es nivel 100; 10 × 101³ = 10.303.010 es nivel 101
        $this->assertSame(100, NivelHelper::nivelDesdeExperiencia(10_000_001));
        $this->assertSame(100, NivelHelper::nivelDesdeExperiencia(10_303_009));
        $this->assertSame(101, NivelHelper::nivelDesdeExperiencia(10_303_010));
        $this->assertSame(101, NivelHelper::nivelDesdeExperiencia(10_303_011));
    }

    public function test_nivel_con_experiencia_grande(): void
    {
        $this->assertSame(50, NivelHelper::nivelDesdeExperiencia(1_250_000)); // 10 × 50³
        $this->assertSame(200, NivelHelper::nivelDesdeExperiencia(80_000_000)); // 10 × 200³, sin tope
    }

    public function test_exp_derrota_sigue_formula_gen_v(): void
    {
        $this->assertSame(64, NivelHelper::expDerrota(64, 5)); // 64*5/5
        $this->assertSame(140, NivelHelper::expDerrota(100, 7)); // 700/5
    }

    public function test_exp_derrota_redondea_hacia_abajo(): void
    {
        $this->assertSame(99, NivelHelper::expDerrota(99, 5)); // 495/5 exacto
        $this->assertSame(198, NivelHelper::expDerrota(99, 10)); // 990/5 exacto
        $this->assertSame(141, NivelHelper::expDerrota(101, 7)); // 707/5 = 141.4 → 141
    }

    public function test_experiencia_para_nivel_sigue_curva_media_x10(): void
    {
        $this->assertSame(10, NivelHelper::experienciaParaNivel(1));
        $this->assertSame(80, NivelHelper::experienciaParaNivel(2));
        $this->assertSame(270, NivelHelper::experienciaParaNivel(3));
        $this->assertSame(640, NivelHelper::experienciaParaNivel(4));
        $this->assertSame(10_000_000, NivelHelper::experienciaParaNivel(100));
        $this->assertSame(10_303_010, NivelHelper::experienciaParaNivel(101));
    }

    public function test_progreso_inicia_en_cero_con_experiencia_baja(): void
    {
        // exp 0..9 → nivel 1 (guard) con inicio en 10 → el crudo sería negativo, se clampa a 0
        $this->assertSame(0, NivelHelper::progresoHaciaSiguienteNivel(0));
        $this->assertSame(0, NivelHelper::progresoHaciaSiguienteNivel(9));
        // exp 10 es el umbral del propio nivel 1 → 0% recién entrado
        $this->assertSame(0, NivelHelper::progresoHaciaSiguienteNivel(10));
    }

    public function test_progreso_mitad_del_rango_es_50(): void
    {
        // nivel 1: rango [10, 80), midpoint 45 → 50%
        $this->assertSame(50, NivelHelper::progresoHaciaSiguienteNivel(45));
        // nivel 2: rango [80, 270), midpoint 175 → 50%
        $this->assertSame(50, NivelHelper::progresoHaciaSiguienteNivel(175));
    }

    public function test_progreso_se_aproxima_a_100_al_final_del_rango(): void
    {
        // 79 de 80 → 98.57 → 99; 269 de 270 → 99.47 → 99
        $this->assertSame(99, NivelHelper::progresoHaciaSiguienteNivel(79));
        $this->assertSame(99, NivelHelper::progresoHaciaSiguienteNivel(269));
        // justo antes del umbral de 101 (10.303.010) → 100%
        $this->assertSame(100, NivelHelper::progresoHaciaSiguienteNivel(10_303_009));
    }

    public function test_progreso_se_reinicia_a_cero_al_entrar_al_siguiente_nivel(): void
    {
        $this->assertSame(0, NivelHelper::progresoHaciaSiguienteNivel(80));
        $this->assertSame(0, NivelHelper::progresoHaciaSiguienteNivel(270));
        $this->assertSame(0, NivelHelper::progresoHaciaSiguienteNivel(10_000_000)); // nivel 100 exacto
    }
}
