<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Src\Exploraciones\Domain\CalculadorFinExploracion;

class CalculadorFinExploracionTest extends TestCase
{
    public function test_hora_futura_el_mismo_dia(): void
    {
        $inicio = Carbon::parse('2026-08-28 10:00:00');

        $fin = CalculadorFinExploracion::calcular('12:30', null, $inicio);

        $this->assertNotNull($fin);
        $this->assertSame('2026-08-28 12:30:00', $fin->format('Y-m-d H:i:s'));
    }

    public function test_hora_igual_al_inicio_pasa_al_dia_siguiente(): void
    {
        $inicio = Carbon::parse('2026-08-28 10:00:00');

        $fin = CalculadorFinExploracion::calcular('10:00', null, $inicio);

        $this->assertNotNull($fin);
        $this->assertSame('2026-08-29 10:00:00', $fin->format('Y-m-d H:i:s'));
    }

    public function test_hora_pasada_pasa_al_dia_siguiente(): void
    {
        $inicio = Carbon::parse('2026-08-28 10:00:00');

        $fin = CalculadorFinExploracion::calcular('08:00', null, $inicio);

        $this->assertNotNull($fin);
        $this->assertSame('2026-08-29 08:00:00', $fin->format('Y-m-d H:i:s'));
    }

    public function test_hora_nocturna_cruza_la_medianoche(): void
    {
        $inicio = Carbon::parse('2026-08-28 23:00:00');

        $fin = CalculadorFinExploracion::calcular('01:00', null, $inicio);

        $this->assertNotNull($fin);
        $this->assertSame('2026-08-29 01:00:00', $fin->format('Y-m-d H:i:s'));
    }

    public function test_duracion_suma_horas_al_inicio(): void
    {
        $inicio = Carbon::parse('2026-08-28 10:00:00');

        $fin = CalculadorFinExploracion::calcular(null, 3, $inicio);

        $this->assertNotNull($fin);
        $this->assertSame('2026-08-28 13:00:00', $fin->format('Y-m-d H:i:s'));
    }

    public function test_sin_hora_ni_duracion_devuelve_null(): void
    {
        $inicio = Carbon::parse('2026-08-28 10:00:00');

        $this->assertNull(CalculadorFinExploracion::calcular(null, null, $inicio));
    }

    public function test_la_hora_tiene_prioridad_sobre_la_duracion(): void
    {
        $inicio = Carbon::parse('2026-08-28 10:00:00');

        $fin = CalculadorFinExploracion::calcular('12:00', 5, $inicio);

        $this->assertNotNull($fin);
        $this->assertSame('2026-08-28 12:00:00', $fin->format('Y-m-d H:i:s'));
    }

    public function test_no_muta_el_inicio(): void
    {
        $inicio = Carbon::parse('2026-08-28 23:00:00');

        CalculadorFinExploracion::calcular('01:00', null, $inicio);

        $this->assertSame('2026-08-28 23:00:00', $inicio->format('Y-m-d H:i:s'));
    }
}
