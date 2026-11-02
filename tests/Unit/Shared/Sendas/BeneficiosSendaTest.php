<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Sendas;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\Sendas\BeneficiosSenda;
use Src\Shared\Domain\Sendas\NivelSenda;

class BeneficiosSendaTest extends TestCase
{
    #[Test]
    public function test_sin_bonus_devuelve_todo_a_cero(): void
    {
        $beneficios = BeneficiosSenda::sinBonus();

        $this->assertSame(0.0, $beneficios->captura);
        $this->assertSame(0.0, $beneficios->danoInfligido);
        $this->assertSame(0.0, $beneficios->danoRecibido);
        $this->assertSame(0.0, $beneficios->carameloTipo);
    }

    /**
     * @return array<string, array{0: NivelSenda, 1: float}>
     */
    public static function beneficiosPorNivel(): array
    {
        return [
            'N1' => [NivelSenda::N1, 0.0],
            'N2' => [NivelSenda::N2, 0.0],
            'N3' => [NivelSenda::N3, 0.0],
            'N4' => [NivelSenda::N4, 0.10],
            'N5' => [NivelSenda::N5, 0.20],
        ];
    }

    #[Test]
    #[DataProvider('beneficiosPorNivel')]
    public function test_por_nivel_aplica_el_mismo_porcentaje_a_los_cuatro_efectos(NivelSenda $nivel, float $esperado): void
    {
        $beneficios = BeneficiosSenda::para($nivel);

        $this->assertSame($esperado, $beneficios->captura);
        $this->assertSame($esperado, $beneficios->danoInfligido);
        $this->assertSame($esperado, $beneficios->danoRecibido);
        $this->assertSame($esperado, $beneficios->carameloTipo);
    }

    #[Test]
    public function test_los_beneficios_de_n5_son_absolutos_y_no_compuestos(): void
    {
        $beneficios = BeneficiosSenda::para(NivelSenda::N5);

        $this->assertSame(0.20, $beneficios->captura);
        $this->assertNotSame(0.21, $beneficios->captura);
    }
    #[Test]
    public function test_porcentajes_configurables_sobrescriben_el_default(): void
    {
        $beneficios = BeneficiosSenda::para(NivelSenda::N5, [5 => 0.15]);

        $this->assertSame(0.15, $beneficios->captura);
        $this->assertSame(0.15, $beneficios->danoInfligido);
        $this->assertSame(0.15, $beneficios->danoRecibido);
        $this->assertSame(0.15, $beneficios->carameloTipo);

        $this->assertSame(0.05, BeneficiosSenda::para(NivelSenda::N4, [4 => 0.05])->captura);
        $this->assertSame(0.20, BeneficiosSenda::para(NivelSenda::N5)->captura);
    }

    #[Test]
    public function test_porcentajes_invalidos_usan_el_default(): void
    {
        $this->assertSame(0.20, BeneficiosSenda::para(NivelSenda::N5, [5 => 1.5])->captura);
        $this->assertSame(0.20, BeneficiosSenda::para(NivelSenda::N5, [5 => -0.1])->captura);
        $this->assertSame(0.20, BeneficiosSenda::para(NivelSenda::N5, [5 => 'alto'])->captura);
        $this->assertSame(0.10, BeneficiosSenda::para(NivelSenda::N4, [4 => 2])->captura);
    }
}
