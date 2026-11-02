<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Src\Exploraciones\Domain\CadenciaEventos;

class CadenciaEventosTest extends TestCase
{
    private const INTERVALO_BASE = 30;

    private function pendientes(
        Carbon $inicio,
        Carbon $hasta,
        int $eventosGenerados = 0,
        int $tiempoPerdido = 0,
        int $intervaloBase = self::INTERVALO_BASE,
        float $reduccion = 0.0,
        int $bonus = 0,
    ): int {
        return CadenciaEventos::eventosPendientes(
            $inicio,
            $hasta,
            $eventosGenerados,
            $tiempoPerdido,
            $intervaloBase,
            $reduccion,
            $bonus,
        );
    }

    public function test_sesenta_minutos_con_intervalo_base_genera_dos_eventos(): void
    {
        $inicio = Carbon::parse('2026-11-02 10:00:00');

        $this->assertSame(2, $this->pendientes($inicio, $inicio->copy()->addMinutes(60)));
    }

    public function test_el_bonus_reduce_el_intervalo_y_multiplica_los_eventos(): void
    {
        $inicio = Carbon::parse('2026-11-02 10:00:00');

        // 30 / (1 + 1) = 15 min → 60 / 15 = 4.
        $this->assertSame(4, $this->pendientes($inicio, $inicio->copy()->addMinutes(60), bonus: 1));
    }

    public function test_menos_del_intervalo_no_genera_eventos(): void
    {
        $inicio = Carbon::parse('2026-11-02 10:00:00');

        $this->assertSame(0, $this->pendientes($inicio, $inicio->copy()->addMinutes(10)));
    }

    public function test_ventana_nula_o_negativa_devuelve_cero(): void
    {
        $inicio = Carbon::parse('2026-11-02 10:00:00');

        $this->assertSame(0, $this->pendientes($inicio, $inicio->copy()));
        $this->assertSame(0, $this->pendientes($inicio, $inicio->copy()->subMinutes(30)));
    }

    public function test_aplica_el_tope_de_doce_eventos_por_procesamiento(): void
    {
        $inicio = Carbon::parse('2026-11-02 10:00:00');

        $this->assertSame(
            CadenciaEventos::MAX_EVENTOS_POR_PROCESAMIENTO,
            $this->pendientes($inicio, $inicio->copy()->addMinutes(600), intervaloBase: 1),
        );
    }

    public function test_resta_los_eventos_ya_generados(): void
    {
        $inicio = Carbon::parse('2026-11-02 10:00:00');

        $this->assertSame(1, $this->pendientes($inicio, $inicio->copy()->addMinutes(60), eventosGenerados: 1));
        $this->assertSame(0, $this->pendientes($inicio, $inicio->copy()->addMinutes(60), eventosGenerados: 2));
    }

    public function test_descuenta_el_tiempo_perdido_del_tiempo_efectivo(): void
    {
        $inicio = Carbon::parse('2026-11-02 10:00:00');

        $this->assertSame(1, $this->pendientes($inicio, $inicio->copy()->addMinutes(60), tiempoPerdido: 30));
        $this->assertSame(0, $this->pendientes($inicio, $inicio->copy()->addMinutes(60), tiempoPerdido: 60));
        $this->assertSame(1, $this->pendientes($inicio, $inicio->copy()->addMinutes(60), tiempoPerdido: 5));
    }

    public function test_sesenta_ticks_de_un_minuto_producen_el_mismo_total_que_un_tick_largo(): void
    {
        $inicio = Carbon::parse('2026-11-02 10:00:00');

        $enUnTick = $this->acumularPorTicks($inicio, [60]);
        $enDoceTicks = $this->acumularPorTicks($inicio, array_fill(0, 12, 5));
        $enCuatroTicks = $this->acumularPorTicks($inicio, array_fill(0, 4, 15));
        $enSesentaTicks = $this->acumularPorTicks($inicio, array_fill(0, 60, 1));

        $this->assertSame(2, $enUnTick);
        $this->assertSame($enUnTick, $enSesentaTicks);
        $this->assertSame($enDoceTicks, $enSesentaTicks);
        $this->assertSame($enCuatroTicks, $enSesentaTicks);
    }

    public function test_un_tick_largo_y_varios_cortos_producen_el_mismo_total(): void
    {
        $inicio = Carbon::parse('2026-11-02 10:00:00');

        $enUnTick = $this->acumularPorTicks($inicio, [60]);
        $enDoceTicks = $this->acumularPorTicks($inicio, array_fill(0, 12, 5));
        $enCuatroTicks = $this->acumularPorTicks($inicio, array_fill(0, 4, 15));

        $this->assertSame(2, $enUnTick);
        $this->assertSame($enUnTick, $enDoceTicks);
        $this->assertSame($enUnTick, $enCuatroTicks);
    }

    /**
     * Simula ticks consecutivos acumulando los eventos programados, como hace
     * el contador persistido `eventos_generados` del handler.
     *
     * @param  list<int>  $minutosPorTick
     */
    private function acumularPorTicks(Carbon $inicio, array $minutosPorTick): int
    {
        $hasta = $inicio->copy();
        $generados = 0;

        foreach ($minutosPorTick as $minutos) {
            $hasta->addMinutes($minutos);
            $generados += $this->pendientes($inicio, $hasta, eventosGenerados: $generados);
        }

        return $generados;
    }
}
