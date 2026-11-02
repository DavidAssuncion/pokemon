<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Src\Exploraciones\Domain\EconomiaRecompensas;
use Src\Exploraciones\Domain\ReglasExploracion;
use Src\Shared\Domain\ReglasEquipo;

class EconomiaRecompensasTest extends TestCase
{
    public function test_legado_conserva_el_factor_y_reparte_la_exp_50_entre_cuenta_y_tres_miembros(): void
    {
        $economia = EconomiaRecompensas::legado();

        $this->assertSame(1.0, $economia->factor);
        $this->assertSame(0.5, $economia->fraccionCuenta);
        $this->assertSame(0.5, $economia->fraccionDestino);
        $this->assertSame(ReglasEquipo::MIEMBROS, $economia->miembrosDestino);
        $this->assertFalse($economia->esIndividual());
        $this->assertSame(1.0, $economia->fraccionCuenta + $economia->fraccionDestino);
    }

    public function test_individual_aplica_el_50_por_ciento_y_el_reparto_50_50(): void
    {
        $economia = EconomiaRecompensas::individual();

        $this->assertSame(0.5, $economia->factor);
        $this->assertSame(0.5, $economia->fraccionCuenta);
        $this->assertSame(0.5, $economia->fraccionDestino);
        $this->assertSame(1, $economia->miembrosDestino);
        $this->assertTrue($economia->esIndividual());
        $this->assertSame(1.0, $economia->fraccionCuenta + $economia->fraccionDestino);
    }

    public function test_las_constantes_de_reglas_reflejan_la_economia_individual(): void
    {
        $this->assertSame(0.5, ReglasExploracion::FACTOR_RECOMPENSAS);
        $this->assertSame(0.5, ReglasExploracion::FRACCION_EXP_INDIVIDUAL);
    }

    public function test_el_constructor_rechaza_fracciones_que_no_suman_uno(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EconomiaRecompensas(
            factor: 1.0,
            fraccionCuenta: 0.6,
            fraccionDestino: 0.6,
            miembrosDestino: 3,
        );
    }

    public function test_el_constructor_rechaza_un_factor_mayor_que_uno(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EconomiaRecompensas(
            factor: 1.5,
            fraccionCuenta: 0.5,
            fraccionDestino: 0.5,
            miembrosDestino: 3,
        );
    }

    public function test_el_constructor_rechaza_cero_miembros_destino(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EconomiaRecompensas(
            factor: 1.0,
            fraccionCuenta: 0.5,
            fraccionDestino: 0.5,
            miembrosDestino: 0,
        );
    }
}
