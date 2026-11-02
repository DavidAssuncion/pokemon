<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\SendaCostes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SendaCostesTest extends TestCase
{
    /**
     * @return array<string, array{0: int, 1: int}>
     */
    public static function costesPorTramo(): array
    {
        return [
            'tramo 1' => [1, 2],
            'tramo 2' => [2, 5],
            'tramo 3' => [3, 10],
            'tramo 4' => [4, 25],
            'tramo 5' => [5, 60],
            'clamp inferior' => [0, 2],
            'clamp negativo' => [-7, 2],
            'clamp superior' => [6, 60],
        ];
    }

    #[Test]
    #[DataProvider('costesPorTramo')]
    public function test_coste_por_tramo(int $tramo, int $esperado): void
    {
        $this->assertSame($esperado, SendaCostes::coste($tramo));
    }

    #[Test]
    public function test_config_permite_ajustar_el_coste_de_un_tramo(): void
    {
        config(['sendas.costes.5' => 70]);

        $this->assertSame(70, SendaCostes::coste(5));
        $this->assertSame(2, SendaCostes::coste(1));
        $this->assertSame(10, SendaCostes::coste(3));
    }

    #[Test]
    public function test_config_incompleta_conserva_los_valores_por_defecto(): void
    {
        config(['sendas.costes' => [1 => 9]]);

        $this->assertSame(9, SendaCostes::coste(1));
        $this->assertSame(5, SendaCostes::coste(2));
        $this->assertSame(60, SendaCostes::coste(5));
    }
}
