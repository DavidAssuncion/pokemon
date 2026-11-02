<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Exploraciones\Domain\DificultadHabitat;
use Src\Shared\Domain\PerfilIvsEvs;

class DificultadHabitatTest extends TestCase
{
    /**
     * @return array<string, array{0: int, 1: PerfilIvsEvs}>
     */
    public static function niveles(): array
    {
        return [
            'nivel 1' => [1, PerfilIvsEvs::Nulo],
            'nivel 2' => [2, PerfilIvsEvs::TopDos],
            'nivel 3' => [3, PerfilIvsEvs::Completo],
            'clamp cero' => [0, PerfilIvsEvs::Nulo],
            'clamp negativo' => [-5, PerfilIvsEvs::Nulo],
            'clamp cuatro' => [4, PerfilIvsEvs::Completo],
            'clamp superior' => [99, PerfilIvsEvs::Completo],
        ];
    }

    #[Test]
    #[DataProvider('niveles')]
    public function test_el_nivel_de_habitat_usa_el_perfil_de_ruta(int $nivel, PerfilIvsEvs $esperado): void
    {
        $dificultad = DificultadHabitat::para($nivel);

        $this->assertSame($esperado, $dificultad->perfil);
    }

    #[Test]
    public function test_los_multiplicadores_corresponden_al_perfil_del_habitat(): void
    {
        $nivelUno = DificultadHabitat::para(1);
        $this->assertSame(1.00, $nivelUno->familia());
        $this->assertSame(1.00, $nivelUno->ev());
        $this->assertSame(1.00, $nivelUno->tipo());

        $nivelDos = DificultadHabitat::para(2);
        $this->assertSame(3.30, $nivelDos->familia());
        $this->assertSame(1.85, $nivelDos->ev());
        $this->assertSame(1.90, $nivelDos->tipo());

        $nivelTres = DificultadHabitat::para(3);
        $this->assertSame(3.35, $nivelTres->familia());
        $this->assertSame(2.80, $nivelTres->ev());
        $this->assertSame(2.40, $nivelTres->tipo());
    }
}
