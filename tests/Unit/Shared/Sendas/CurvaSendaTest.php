<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Sendas;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\Sendas\CurvaSenda;

class CurvaSendaTest extends TestCase
{
    #[Test]
    public function test_constantes_de_la_curva(): void
    {
        $this->assertSame(72_000, CurvaSenda::EXP_MAXIMA);
        $this->assertSame(100, CurvaSenda::EXP_POR_CARAMELO);
        $this->assertSame(5, CurvaSenda::NIVEL_MAXIMO);
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
            'clamp negativo' => [-4, 0],
            'tope' => [6, 72_000],
        ];
    }

    #[Test]
    #[DataProvider('umbrales')]
    public function test_umbral(int $nivel, int $esperado): void
    {
        $this->assertSame($esperado, CurvaSenda::umbral($nivel));
    }

    /**
     * @return array<string, array{0: int, 1: int}>
     */
    public static function niveles(): array
    {
        return [
            'negativo' => [-100, 1],
            'cero' => [0, 1],
            'justo antes de N2' => [8_999, 1],
            'N2' => [9_000, 2],
            'justo antes de N3' => [17_999, 2],
            'N3' => [18_000, 3],
            'justo antes de N4' => [35_999, 3],
            'N4' => [36_000, 4],
            'justo antes de N5' => [71_999, 4],
            'N5' => [72_000, 5],
            'por encima del tope' => [500_000, 5],
        ];
    }

    #[Test]
    #[DataProvider('niveles')]
    public function test_nivel(int $exp, int $esperado): void
    {
        $this->assertSame($esperado, CurvaSenda::nivel($exp));
    }
}
