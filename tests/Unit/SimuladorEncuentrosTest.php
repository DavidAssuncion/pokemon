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
            fn () => 0.85 // 85 → contratiempo
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
        // Timezone explícito UTC: este test no arranca la app Laravel (extends
        // PHPUnit TestCase) y el timezone por defecto del proceso depende del
        // orden de ejecución. Con UTC explícito el resultado es determinista.
        $inicio = Carbon::parse('2026-08-28 10:00:00', 'UTC');
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

    public function test_tipo_encuentro_normal_con_pool_ponderado(): void
    {
        // 0.3 → 30 < 45 → encuentro; subtipo 30 → normal; pick 0.3 → id 1.
        $eventos = SimuladorEncuentros::generarEventos(
            SimuladorEncuentros::poolPonderado($this->poolBase()),
            1,
            Carbon::parse('2026-08-28 10:00:00'),
            Carbon::parse('2026-08-28 10:05:00'),
            fn () => 0.3
        );

        $this->assertSame('encuentro', $eventos[0]['tipo']);
        $this->assertSame('normal', $eventos[0]['subtype']);
        $this->assertSame(1, $eventos[0]['pokemon_id']);
    }

    public function test_tipo_encuentro_grupo(): void
    {
        // 0.15 → 15 < 45 → encuentro; subtipo 15 → grupo (10–20).
        $eventos = SimuladorEncuentros::generarEventos(
            SimuladorEncuentros::poolPonderado($this->poolBase()),
            1,
            Carbon::parse('2026-08-28 10:00:00'),
            Carbon::parse('2026-08-28 10:05:00'),
            fn () => 0.15
        );

        $this->assertSame('encuentro', $eventos[0]['tipo']);
        $this->assertSame('grupo', $eventos[0]['subtype']);
        $this->assertSame(1, $eventos[0]['pokemon_id']);
    }

    public function test_tipo_encuentro_excepcional(): void
    {
        // 0.08 → 8 < 45 → encuentro; subtipo 8 → excepcional (7–10).
        $eventos = SimuladorEncuentros::generarEventos(
            SimuladorEncuentros::poolPonderado($this->poolBase()),
            1,
            Carbon::parse('2026-08-28 10:00:00'),
            Carbon::parse('2026-08-28 10:05:00'),
            fn () => 0.08
        );

        $this->assertSame('encuentro', $eventos[0]['tipo']);
        $this->assertSame('excepcional', $eventos[0]['subtype']);
    }

    public function test_tipo_emboscada_desde_el_subtipo_encuentro(): void
    {
        // 0.05 → 5 < 45 → encuentro; subtipo 5 → emboscada (< 7) con pokemon_ids.
        $eventos = SimuladorEncuentros::generarEventos(
            SimuladorEncuentros::poolPonderado($this->poolBase()),
            1,
            Carbon::parse('2026-08-28 10:00:00'),
            Carbon::parse('2026-08-28 10:05:00'),
            fn () => 0.05
        );

        $this->assertSame('emboscada', $eventos[0]['tipo']);
        $this->assertIsArray($eventos[0]['pokemon_ids']);
        $this->assertCount(2, $eventos[0]['pokemon_ids']); // 0.05 < 0.5 → 2
    }

    public function test_tipo_emboscada_desde_encuentro_especial(): void
    {
        // 0.7 → 70 → encuentro especial (65–80) → emboscada con pokemon_ids (2–3).
        $eventos = SimuladorEncuentros::generarEventos(
            SimuladorEncuentros::poolPonderado($this->poolBase()),
            1,
            Carbon::parse('2026-08-28 10:00:00'),
            Carbon::parse('2026-08-28 10:05:00'),
            fn () => 0.7
        );

        $this->assertSame('emboscada', $eventos[0]['tipo']);
        $this->assertIsArray($eventos[0]['pokemon_ids']);
        $this->assertContains(count($eventos[0]['pokemon_ids']), [2, 3]);
        foreach ($eventos[0]['pokemon_ids'] as $id) {
            $this->assertContains($id, [1, 2]);
        }
    }

    public function test_tipo_hallazgo_caramelo_familia_lleva_pokemon_y_cantidad(): void
    {
        // Secuencia: jitter timestamp (0.5), tipo (0.48 → hallazgo), roll subtipo (0.1 → familia), pick (0.5).
        $secuencia = [0.5, 0.48, 0.1, 0.5];
        $eventos = SimuladorEncuentros::generarEventos(
            SimuladorEncuentros::poolPonderado($this->poolBase()),
            1,
            Carbon::parse('2026-08-28 10:00:00'),
            Carbon::parse('2026-08-28 10:05:00'),
            function () use (&$secuencia): float {
                return $secuencia === [] ? 0.5 : array_shift($secuencia);
            }
        );

        $this->assertSame('hallazgo', $eventos[0]['tipo']);
        $this->assertSame('caramelo_familia', $eventos[0]['subtype']);
        $this->assertSame(1, $eventos[0]['cantidad']);
        $this->assertArrayHasKey('pokemon_id', $eventos[0]);
    }

    public function test_tipo_hallazgo_caramelo_ev_lleva_stat_valido(): void
    {
        // 0.6 → 60 → hallazgo; roll 60 → caramelo_ev (33–66).
        $eventos = SimuladorEncuentros::generarEventos(
            SimuladorEncuentros::poolPonderado($this->poolBase()),
            1,
            Carbon::parse('2026-08-28 10:00:00'),
            Carbon::parse('2026-08-28 10:05:00'),
            fn () => 0.6
        );

        $this->assertSame('hallazgo', $eventos[0]['tipo']);
        $this->assertSame('caramelo_ev', $eventos[0]['subtype']);
        $this->assertSame(1, $eventos[0]['cantidad']);
        $this->assertGreaterThanOrEqual(1, $eventos[0]['stat']);
        $this->assertLessThanOrEqual(6, $eventos[0]['stat']);
    }

    public function test_tipo_hallazgo_caramelo_tipo_lleva_tipo_id_valido(): void
    {
        // Secuencia: jitter (0.5), tipo (0.55 → hallazgo), roll subtipo (0.9 → caramelo_tipo), pick tipo (0.5).
        $secuencia = [0.5, 0.55, 0.9, 0.5];
        $eventos = SimuladorEncuentros::generarEventos(
            SimuladorEncuentros::poolPonderado($this->poolBase()),
            1,
            Carbon::parse('2026-08-28 10:00:00'),
            Carbon::parse('2026-08-28 10:05:00'),
            function () use (&$secuencia): float {
                return $secuencia === [] ? 0.5 : array_shift($secuencia);
            }
        );

        $this->assertSame('hallazgo', $eventos[0]['tipo']);
        $this->assertSame('caramelo_tipo', $eventos[0]['subtype']);
        $this->assertSame(1, $eventos[0]['cantidad']);
        $this->assertGreaterThanOrEqual(1, $eventos[0]['tipo_id']);
        $this->assertLessThanOrEqual(18, $eventos[0]['tipo_id']);
    }

    public function test_tipo_contratiempo_tiene_subtipo_conocido(): void
    {
        $eventos = SimuladorEncuentros::generarEventos(
            SimuladorEncuentros::poolPonderado($this->poolBase()),
            1,
            Carbon::parse('2026-08-28 10:00:00'),
            Carbon::parse('2026-08-28 10:05:00'),
            fn () => 0.85 // 85 → contratiempo
        );

        $this->assertSame('contratiempo', $eventos[0]['tipo']);
        $this->assertContains($eventos[0]['subtype'], ['desorientacion', 'terreno', 'clima', 'bloqueo']);
    }

    public function test_tipo_neutral_en_evento_neutral(): void
    {
        // 0.95 → 95 ≥ 90 → evento neutral.
        $eventos = SimuladorEncuentros::generarEventos(
            SimuladorEncuentros::poolPonderado($this->poolBase()),
            1,
            Carbon::parse('2026-08-28 10:00:00'),
            Carbon::parse('2026-08-28 10:05:00'),
            fn () => 0.95
        );

        $this->assertSame('neutral', $eventos[0]['tipo']);
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
        $this->assertSame(45, SimuladorEncuentros::PROBABILIDAD_ENCUENTRO);
        $this->assertSame(20, SimuladorEncuentros::PROBABILIDAD_HALLAZGO);
        $this->assertSame(15, SimuladorEncuentros::PROBABILIDAD_ENCUENTRO_ESPECIAL);
        $this->assertSame(10, SimuladorEncuentros::PROBABILIDAD_CONTRATIEMPO);
        $this->assertSame(10, SimuladorEncuentros::PROBABILIDAD_NEUTRAL);
        $this->assertSame(80, SimuladorEncuentros::SUBTIPO_NORMAL);
        $this->assertSame(10, SimuladorEncuentros::SUBTIPO_GRUPO);
        $this->assertSame(7, SimuladorEncuentros::SUBTIPO_EMBOSCADA);
        $this->assertSame(3, SimuladorEncuentros::SUBTIPO_EXCEPCIONAL);
    }
}
