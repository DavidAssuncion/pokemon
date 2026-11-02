<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\PerfilIvsEvs;

class PerfilIvsEvsTest extends TestCase
{
    #[Test]
    public function test_los_valores_de_cadena_son_estables(): void
    {
        $this->assertSame('nulo', PerfilIvsEvs::Nulo->value);
        $this->assertSame('top_uno', PerfilIvsEvs::TopUno->value);
        $this->assertSame('top_dos', PerfilIvsEvs::TopDos->value);
        $this->assertSame('completo', PerfilIvsEvs::Completo->value);
    }

    /**
     * @return array<string, array{0: PerfilIvsEvs, 1: int, 2: int, 3: int, 4: int, 5: int}>
     */
    public static function perfiles(): array
    {
        return [
            'nulo' => [PerfilIvsEvs::Nulo, 0, 15, 255, 0, 0],
            'top_uno' => [PerfilIvsEvs::TopUno, 1, 15, 255, 0, 0],
            'top_dos' => [PerfilIvsEvs::TopDos, 2, 15, 255, 8, 128],
            'completo' => [PerfilIvsEvs::Completo, 6, 15, 255, 15, 255],
        ];
    }

    #[Test]
    #[DataProvider('perfiles')]
    public function test_cada_perfil_expone_sus_maximos_y_restos(
        PerfilIvsEvs $perfil,
        int $cuantosMaximos,
        int $ivMaximo,
        int $evMaximo,
        int $ivResto,
        int $evResto,
    ): void {
        $this->assertSame($cuantosMaximos, $perfil->cuantosMaximos());
        $this->assertSame($ivMaximo, $perfil->ivMaximo());
        $this->assertSame($evMaximo, $perfil->evMaximo());
        $this->assertSame($ivResto, $perfil->ivResto());
        $this->assertSame($evResto, $perfil->evResto());
    }

    #[Test]
    public function test_solo_el_perfil_completo_llena_los_seis_stats(): void
    {
        $this->assertSame(6, PerfilIvsEvs::Completo->cuantosMaximos());
        $this->assertSame(15, PerfilIvsEvs::Completo->ivResto());
        $this->assertSame(255, PerfilIvsEvs::Completo->evResto());
    }
}
