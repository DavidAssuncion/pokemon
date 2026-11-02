<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\MultiplicadoresDificultad;
use Src\Shared\Domain\PerfilIvsEvs;

class MultiplicadoresDificultadTest extends TestCase
{
    /**
     * @return array<string, array{0: PerfilIvsEvs, 1: float, 2: float, 3: float}>
     */
    public static function perfiles(): array
    {
        return [
            'nulo' => [PerfilIvsEvs::Nulo, 1.00, 1.00, 1.00],
            'top uno' => [PerfilIvsEvs::TopUno, 1.00, 1.00, 1.00],
            'top dos' => [PerfilIvsEvs::TopDos, 3.30, 1.85, 1.90],
            'completo' => [PerfilIvsEvs::Completo, 3.35, 2.80, 2.40],
        ];
    }

    #[Test]
    #[DataProvider('perfiles')]
    public function test_expone_los_multiplicadores_del_perfil(
        PerfilIvsEvs $perfil,
        float $familia,
        float $ev,
        float $tipo,
    ): void {
        $multiplicadores = MultiplicadoresDificultad::para($perfil);

        $this->assertSame($perfil, $multiplicadores->perfil);
        $this->assertSame($familia, $multiplicadores->familia());
        $this->assertSame($ev, $multiplicadores->ev());
        $this->assertSame($tipo, $multiplicadores->tipo());
    }

    #[Test]
    public function test_sin_dificultad_usa_perfil_nulo_y_multiplicadores_unitarios(): void
    {
        $multiplicadores = MultiplicadoresDificultad::sinDificultad();

        $this->assertSame(PerfilIvsEvs::Nulo, $multiplicadores->perfil);
        $this->assertSame(1.00, $multiplicadores->familia());
        $this->assertSame(1.00, $multiplicadores->ev());
        $this->assertSame(1.00, $multiplicadores->tipo());
    }

    #[Test]
    public function test_el_constructor_directo_equivale_al_alias_para(): void
    {
        $directo = new MultiplicadoresDificultad(PerfilIvsEvs::Completo);
        $alias = MultiplicadoresDificultad::para(PerfilIvsEvs::Completo);

        $this->assertSame($directo->perfil, $alias->perfil);
        $this->assertSame($directo->familia(), $alias->familia());
        $this->assertSame($directo->ev(), $alias->ev());
        $this->assertSame($directo->tipo(), $alias->tipo());
    }
}
