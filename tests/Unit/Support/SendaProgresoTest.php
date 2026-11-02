<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\SendaProgreso;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SendaProgresoTest extends TestCase
{
    #[Test]
    public function test_constantes_por_defecto(): void
    {
        $this->assertSame(5, SendaProgreso::NIVEL_MAXIMO);
        $this->assertSame(72_000, SendaProgreso::EXP_MAXIMA);
        $this->assertSame(100, SendaProgreso::EXP_POR_CARAMELO);
    }

    /**
     * @return array<string, array{0: int, 1: int}>
     */
    public static function umbrales(): array
    {
        return [
            'nivel 1' => [1, 0],
            'nivel 2' => [2, 9_000],
            'nivel 3' => [3, 18_000],
            'nivel 4' => [4, 36_000],
            'nivel 5' => [5, 72_000],
            'clamp inferior' => [0, 0],
            'tope' => [6, 72_000],
        ];
    }

    #[Test]
    #[DataProvider('umbrales')]
    public function test_umbral(int $nivel, int $esperado): void
    {
        $this->assertSame($esperado, SendaProgreso::umbral($nivel));
    }

    /**
     * @return array<string, array{0: int, 1: int}>
     */
    public static function niveles(): array
    {
        return [
            'cero' => [0, 1],
            'justo antes de N2' => [8_999, 1],
            'N2' => [9_000, 2],
            'justo antes de N3' => [17_999, 2],
            'N3' => [18_000, 3],
            'N4' => [36_000, 4],
            'N5' => [72_000, 5],
            'por encima del tope' => [500_000, 5],
        ];
    }

    #[Test]
    #[DataProvider('niveles')]
    public function test_nivel(int $exp, int $esperado): void
    {
        $this->assertSame($esperado, SendaProgreso::nivel($exp));
    }

    #[Test]
    public function test_resumen_al_inicio(): void
    {
        $this->assertSame([
            'exp' => 0,
            'exp_maxima' => 72_000,
            'nivel' => 1,
            'nivel_maximo' => 5,
            'exp_siguiente' => 9_000,
            'exp_faltante' => 9_000,
            'porcentaje' => 0,
            'exp_por_caramelo' => 100,
        ], SendaProgreso::resumen(0));
    }

    #[Test]
    public function test_resumen_a_mitad_del_nivel_dos(): void
    {
        $resumen = SendaProgreso::resumen(13_500);

        $this->assertSame(2, $resumen['nivel']);
        $this->assertSame(18_000, $resumen['exp_siguiente']);
        $this->assertSame(4_500, $resumen['exp_faltante']);
        $this->assertSame(50, $resumen['porcentaje']);
    }

    #[Test]
    public function test_resumen_en_el_tope(): void
    {
        $resumen = SendaProgreso::resumen(72_000);

        $this->assertSame(72_000, $resumen['exp']);
        $this->assertSame(5, $resumen['nivel']);
        $this->assertSame(72_000, $resumen['exp_siguiente']);
        $this->assertSame(0, $resumen['exp_faltante']);
        $this->assertSame(100, $resumen['porcentaje']);
    }

    #[Test]
    public function test_resumen_recorta_la_experiencia_por_encima_del_tope(): void
    {
        $resumen = SendaProgreso::resumen(500_000);

        $this->assertSame(72_000, $resumen['exp']);
        $this->assertSame(5, $resumen['nivel']);
    }

    #[Test]
    public function test_config_permite_ajustar_umbrales(): void
    {
        config(['sendas.umbrales.2' => 5_000]);

        $this->assertSame(5_000, SendaProgreso::umbral(2));
        $this->assertSame(2, SendaProgreso::nivel(5_000));
        $this->assertSame(1, SendaProgreso::nivel(4_999));
    }

    #[Test]
    public function test_config_permite_ajustar_la_experiencia(): void
    {
        config([
            'sendas.experiencia.maxima' => 100_000,
            'sendas.experiencia.por_caramelo' => 50,
        ]);

        $resumen = SendaProgreso::resumen(100_000);

        $this->assertSame(100_000, $resumen['exp_maxima']);
        $this->assertSame(50, $resumen['exp_por_caramelo']);
        $this->assertSame(100_000, SendaProgreso::umbral(6));
    }
}
