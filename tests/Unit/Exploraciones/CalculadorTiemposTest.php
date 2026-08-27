<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Src\Exploraciones\Domain\CalculadorTiempos;

class CalculadorTiemposTest extends TestCase
{
    public function test_vuelta_de_cuatro_horas_empieza_una_hora_antes_del_fin(): void
    {
        $inicio = Carbon::parse('2026-08-28 10:00:00');
        $fin = Carbon::parse('2026-08-28 14:00:00');

        $vuelta = CalculadorTiempos::inicioVuelta($inicio, $fin);

        $this->assertSame('2026-08-28 13:00:00', $vuelta->format('Y-m-d H:i:s'));
    }

    public function test_vuelta_de_una_hora_empieza_quince_minutos_antes_del_fin(): void
    {
        $inicio = Carbon::parse('2026-08-28 10:00:00');
        $fin = Carbon::parse('2026-08-28 11:00:00');

        $vuelta = CalculadorTiempos::inicioVuelta($inicio, $fin);

        $this->assertSame('2026-08-28 10:45:00', $vuelta->format('Y-m-d H:i:s'));
    }

    public function test_duracion_cero_devuelve_el_mismo_momento(): void
    {
        $momento = Carbon::parse('2026-08-28 10:00:00');

        $vuelta = CalculadorTiempos::inicioVuelta($momento, $momento->copy());

        $this->assertTrue($vuelta->equalTo($momento));
    }

    public function test_inicio_vuelta_no_muta_los_argumentos(): void
    {
        $inicio = Carbon::parse('2026-08-28 10:00:00');
        $fin = Carbon::parse('2026-08-28 14:00:00');

        CalculadorTiempos::inicioVuelta($inicio, $fin);

        $this->assertSame('2026-08-28 14:00:00', $fin->format('Y-m-d H:i:s'));
    }

    public function test_progreso_en_el_inicio_es_cero(): void
    {
        $inicio = Carbon::parse('2026-08-28 10:00:00');
        $fin = $inicio->copy()->addHours(4);

        $this->assertSame(0, CalculadorTiempos::progreso($inicio, $fin, $inicio->copy()));
    }

    public function test_progreso_a_la_mitad_es_cincuenta(): void
    {
        $inicio = Carbon::parse('2026-08-28 10:00:00');
        $fin = $inicio->copy()->addHours(4);

        $this->assertSame(50, CalculadorTiempos::progreso($inicio, $fin, $inicio->copy()->addHours(2)));
    }

    public function test_progreso_en_el_fin_es_cien(): void
    {
        $inicio = Carbon::parse('2026-08-28 10:00:00');
        $fin = $inicio->copy()->addHours(4);

        $this->assertSame(100, CalculadorTiempos::progreso($inicio, $fin, $fin->copy()));
    }

    public function test_progreso_se_recorta_a_cien_cuando_ahora_supera_el_fin(): void
    {
        $inicio = Carbon::parse('2026-08-28 10:00:00');
        $fin = $inicio->copy()->addHours(4);

        $this->assertSame(100, CalculadorTiempos::progreso($inicio, $fin, $fin->copy()->addDay()));
    }

    public function test_duracion_cero_devuelve_progreso_cien(): void
    {
        $momento = Carbon::parse('2026-08-28 10:00:00');

        $this->assertSame(100, CalculadorTiempos::progreso($momento, $momento->copy(), $momento->copy()));
    }
}
