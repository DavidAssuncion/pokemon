<?php

declare(strict_types=1);

namespace Tests\Unit;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Src\Exploraciones\Domain\SimuladorEncuentros;

class SimuladorEncuentrosTest extends TestCase
{
    /**
     * @return array<int, array{id: int, capture_rate: int, hatch: int|null}>
     */
    private function poolBase(): array
    {
        return [
            ['id' => 1, 'capture_rate' => 60, 'hatch' => 10],
            ['id' => 2, 'capture_rate' => 30, 'hatch' => 10],
        ];
    }

    public function test_peso_es_capture_rate_entre_hatch(): void
    {
        $pool = SimuladorEncuentros::poolPonderado($this->poolBase());

        $this->assertCount(2, $pool);
        $this->assertEqualsWithDelta(6.0, $pool[0]['peso'], 0.0001);
        $this->assertEqualsWithDelta(3.0, $pool[1]['peso'], 0.0001);
    }

    public function test_hatch_mayor_reduce_peso(): void
    {
        $pool = SimuladorEncuentros::poolPonderado([
            ['id' => 1, 'capture_rate' => 60, 'hatch' => 10],
            ['id' => 2, 'capture_rate' => 60, 'hatch' => 30],
        ]);

        $this->assertEqualsWithDelta(6.0, $pool[0]['peso'], 0.0001);
        $this->assertEqualsWithDelta(2.0, $pool[1]['peso'], 0.0001);
    }

    public function test_hatch_nulo_trata_como_uno(): void
    {
        $pool = SimuladorEncuentros::poolPonderado([
            ['id' => 1, 'capture_rate' => 60, 'hatch' => null],
        ]);

        $this->assertCount(1, $pool);
        $this->assertEqualsWithDelta(60.0, $pool[0]['peso'], 0.0001);
    }

    public function test_capture_rate_cero_se_excluye_del_pool(): void
    {
        $pool = SimuladorEncuentros::poolPonderado([
            ['id' => 1, 'capture_rate' => 0, 'hatch' => 10],
            ['id' => 2, 'capture_rate' => 60, 'hatch' => 10],
        ]);

        $this->assertCount(1, $pool);
        $this->assertSame(2, $pool[0]['id']);
    }

    public function test_elegir_ponderado_prefiere_mayor_peso(): void
    {
        $pool = [
            ['id' => 1, 'peso' => 9.0],
            ['id' => 2, 'peso' => 1.0],
        ];

        $this->assertSame(1, SimuladorEncuentros::elegirPonderado($pool, fn () => 0.0)['id']);
        $this->assertSame(1, SimuladorEncuentros::elegirPonderado($pool, fn () => 0.5)['id']);
        $this->assertSame(1, SimuladorEncuentros::elegirPonderado($pool, fn () => 0.89)['id']);
        $this->assertSame(2, SimuladorEncuentros::elegirPonderado($pool, fn () => 0.91)['id']);
        $this->assertSame(2, SimuladorEncuentros::elegirPonderado($pool, fn () => 0.999)['id']);
    }

    public function test_elegir_ponderado_vacio_devuelve_null(): void
    {
        $this->assertNull(SimuladorEncuentros::elegirPonderado([], fn () => 0.5));
    }

    public function test_mayor_capture_rate_se_elige_mas_veces_estadisticamente(): void
    {
        $pool = [
            ['id' => 1, 'peso' => 4.0],
            ['id' => 2, 'peso' => 1.0],
        ];

        $conteos = [1 => 0, 2 => 0];
        for ($i = 0; $i < 2000; $i++) {
            $elegido = SimuladorEncuentros::elegirPonderado($pool, fn () => mt_rand(0, 999) / 1000);
            $conteos[$elegido['id']]++;
        }

        $this->assertGreaterThan(2 * $conteos[2], $conteos[1]);
    }

    public function test_genera_exactamente_los_eventos_pedidos(): void
    {
        $inicio = Carbon::parse('2026-08-28 10:00:00');
        $fin = $inicio->copy()->addMinutes(25);

        $eventos = SimuladorEncuentros::generarEventos(
            SimuladorEncuentros::poolPonderado($this->poolBase()),
            5,
            $inicio,
            $fin,
            fn () => 0.9 // tipo caramelo_ev
        );

        $this->assertCount(5, $eventos);
    }

    public function test_eventos_quedan_dentro_del_intervalo(): void
    {
        $inicio = Carbon::parse('2026-08-28 10:00:00');
        $fin = $inicio->copy()->addMinutes(25);

        $eventos = SimuladorEncuentros::generarEventos(
            SimuladorEncuentros::poolPonderado($this->poolBase()),
            5,
            $inicio,
            $fin,
            fn () => 0.9
        );

        foreach ($eventos as $evento) {
            $timestamp = Carbon::parse($evento['timestamp']);
            $this->assertTrue($timestamp->greaterThanOrEqualTo($inicio));
            $this->assertTrue($timestamp->lessThanOrEqualTo($fin));
        }
    }

    public function test_timestamps_se_reparten_en_slots_de_cinco_minutos(): void
    {
        $inicio = Carbon::parse('2026-08-28 10:00:00');
        $fin = $inicio->copy()->addMinutes(25);

        // aleatorio fijo 0.5 → jitter de 150s dentro de cada slot de 300s
        $eventos = SimuladorEncuentros::generarEventos(
            SimuladorEncuentros::poolPonderado($this->poolBase()),
            5,
            $inicio,
            $fin,
            fn () => 0.5
        );

        $this->assertSame('2026-08-28T10:02:30+00:00', $eventos[0]['timestamp']);
        $this->assertSame('2026-08-28T10:07:30+00:00', $eventos[1]['timestamp']);
        $this->assertSame('2026-08-28T10:22:30+00:00', $eventos[4]['timestamp']);
    }

    public function test_tipo_pokemon_con_pool_ponderado(): void
    {
        $pool = SimuladorEncuentros::poolPonderado($this->poolBase());
        $eventos = SimuladorEncuentros::generarEventos(
            $pool,
            1,
            Carbon::parse('2026-08-28 10:00:00'),
            Carbon::parse('2026-08-28 10:05:00'),
            fn () => 0.1 // roll 10 → pokemon; pick roll 10 → id 1
        );

        $this->assertSame('pokemon', $eventos[0]['tipo']);
        $this->assertSame(1, $eventos[0]['pokemon_id']);
    }

    public function test_tipo_caramelo_familia_lleva_pokemon_y_cantidad(): void
    {
        $pool = SimuladorEncuentros::poolPonderado($this->poolBase());
        $eventos = SimuladorEncuentros::generarEventos(
            $pool,
            1,
            Carbon::parse('2026-08-28 10:00:00'),
            Carbon::parse('2026-08-28 10:05:00'),
            fn () => 0.7 // roll 70 → caramelo_familia
        );

        $this->assertSame('caramelo_familia', $eventos[0]['tipo']);
        $this->assertSame(1, $eventos[0]['cantidad']);
        $this->assertArrayHasKey('pokemon_id', $eventos[0]);
    }

    public function test_tipo_caramelo_ev_lleva_stat_valido(): void
    {
        $pool = SimuladorEncuentros::poolPonderado($this->poolBase());
        $eventos = SimuladorEncuentros::generarEventos(
            $pool,
            1,
            Carbon::parse('2026-08-28 10:00:00'),
            Carbon::parse('2026-08-28 10:05:00'),
            fn () => 0.9 // roll 90 → caramelo_ev
        );

        $this->assertSame('caramelo_ev', $eventos[0]['tipo']);
        $this->assertSame(1, $eventos[0]['cantidad']);
        $this->assertGreaterThanOrEqual(1, $eventos[0]['stat']);
        $this->assertLessThanOrEqual(6, $eventos[0]['stat']);
    }

    public function test_sin_encuentros_devuelve_vacio(): void
    {
        $inicio = Carbon::parse('2026-08-28 10:00:00');

        $eventos = SimuladorEncuentros::generarEventos(
            SimuladorEncuentros::poolPonderado($this->poolBase()),
            0,
            $inicio,
            $inicio->copy()->addMinutes(5),
            fn () => 0.9
        );

        $this->assertSame([], $eventos);
    }

    public function test_pool_vacio_no_genera_eventos(): void
    {
        $eventos = SimuladorEncuentros::generarEventos(
            [],
            5,
            Carbon::parse('2026-08-28 10:00:00'),
            Carbon::parse('2026-08-28 10:25:00'),
            fn () => 0.9
        );

        $this->assertSame([], $eventos);
    }

    public function test_probabilidades_son_configurables(): void
    {
        $this->assertSame(60, SimuladorEncuentros::PROBABILIDAD_POKEMON);
        $this->assertSame(20, SimuladorEncuentros::PROBABILIDAD_CARAMELO_FAMILIA);
        $this->assertSame(20, SimuladorEncuentros::PROBABILIDAD_CARAMELO_EV);
    }
}
