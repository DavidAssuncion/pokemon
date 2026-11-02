<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Src\Exploraciones\Presentation\DiarioExploracion;

final class DiarioExploracionTest extends TestCase
{
    public function test_sin_eventos_devuelve_diario_vacio(): void
    {
        $diario = DiarioExploracion::toView([]);

        $this->assertSame(0, $diario['total']);
        $this->assertSame([], $diario['eventos']);
        $this->assertSame([], $diario['grupos']);
        $this->assertSame([], $diario['hitos']);
    }

    public function test_fusiona_eventos_raiz_y_ordena_por_timestamp(): void
    {
        $diario = DiarioExploracion::toView([
            'bitacora' => [
                ['tipo' => 'descanso', 'timestamp' => '2026-03-02T10:00:00+00:00', 'duracion_minutos' => 5, 'hp_recuperado' => 10],
                ['tipo' => 'neutral', 'timestamp' => '2026-03-02T09:00:00+00:00'],
            ],
            'retirada' => ['reason' => 'grupo_enemigo', 'timestamp' => '2026-03-02T11:00:00+00:00'],
        ]);

        $this->assertSame(3, $diario['total']);
        $this->assertSame('neutral', $diario['eventos'][0]['tipo']);
        $this->assertSame('descanso', $diario['eventos'][1]['tipo']);
        $this->assertSame('retirada', $diario['eventos'][2]['tipo']);
        $this->assertSame('El explorador se retira: grupo_enemigo', $diario['eventos'][2]['texto']);
    }

    public function test_no_duplica_el_evento_raiz_si_ya_esta_en_bitacora(): void
    {
        $diario = DiarioExploracion::toView([
            'bitacora' => [
                ['tipo' => 'retirada', 'reason' => 'grupo_enemigo', 'timestamp' => '2026-03-02T11:00:00+00:00'],
            ],
            'retirada' => ['reason' => 'grupo_enemigo', 'timestamp' => '2026-03-02T11:00:00+00:00'],
        ]);

        $this->assertSame(1, $diario['total']);
    }

    public function test_agrupa_por_fecha_local_con_hoy_ayer_y_fecha(): void
    {
        $ahora = new DateTimeImmutable('now');
        $ayer = $ahora->modify('-1 day');
        $antiguo = new DateTimeImmutable('2024-03-05T12:00:00+00:00');

        $diario = DiarioExploracion::toView([
            'bitacora' => [
                ['tipo' => 'neutral', 'timestamp' => $ahora->format('c')],
                ['tipo' => 'neutral', 'timestamp' => $ayer->format('c')],
                ['tipo' => 'neutral', 'timestamp' => $antiguo->format('c')],
            ],
        ]);

        $this->assertSame(3, $diario['total']);
        $this->assertSame(['05/03/2024', 'Ayer', 'Hoy'], array_column($diario['grupos'], 'etiqueta'));
        $this->assertSame($antiguo->format('Y-m-d'), $diario['grupos'][0]['fecha']);
        $this->assertSame(1, count($diario['grupos'][2]['eventos']));
    }

    public function test_eventos_sin_timestamp_van_al_final(): void
    {
        $diario = DiarioExploracion::toView([
            'bitacora' => [
                ['tipo' => 'neutral'],
                ['tipo' => 'neutral', 'timestamp' => '2026-03-02T09:00:00+00:00'],
            ],
        ]);

        $this->assertSame('2026-03-02T09:00:00+00:00', $diario['eventos'][0]['timestamp']);
        $this->assertArrayNotHasKey('timestamp', $diario['eventos'][1]);
        $this->assertSame('Sin fecha', $diario['grupos'][1]['etiqueta']);
    }

    public function test_anade_explorador_nombre_a_los_eventos(): void
    {
        $diario = DiarioExploracion::toView([
            'bitacora' => [
                ['tipo' => 'descanso', 'timestamp' => '2026-03-02T10:00:00+00:00', 'duracion_minutos' => 5, 'hp_recuperado' => 10],
            ],
        ], [], 'Ana');

        $this->assertSame('Ana', $diario['eventos'][0]['explorador_nombre']);
        $this->assertSame('Ana descansa y recupera 10 HP', $diario['eventos'][0]['texto']);
    }

    public function test_incluye_evento_cancelada_de_raiz(): void
    {
        $diario = DiarioExploracion::toView([
            'cancelada' => ['motivo' => 'manual', 'timestamp' => '2026-03-02T10:00:00+00:00'],
        ]);

        $this->assertSame(1, $diario['total']);
        $this->assertSame('cancelada', $diario['eventos'][0]['tipo']);
        $this->assertSame('Expedición cancelada: manual', $diario['eventos'][0]['texto']);
    }

    public function test_incluye_hitos_del_resultado(): void
    {
        $diario = DiarioExploracion::toView([
            'resultado' => ['duration_real' => 30],
        ]);

        $this->assertSame('expedicion_express', $diario['hitos'][0]['codigo']);
    }

    public function test_evento_oculto_no_aparece_en_eventos_total_ni_grupos(): void
    {
        $diario = DiarioExploracion::toView([
            'bitacora' => [
                ['tipo' => 'hallazgo', 'subtype' => 'caramelo_tipo', 'tipo_id' => 999, 'timestamp' => '2026-03-02T09:00:00+00:00'],
                ['tipo' => 'neutral', 'timestamp' => '2026-03-02T10:00:00+00:00'],
            ],
        ]);

        $this->assertSame(1, $diario['total']);
        $this->assertCount(1, $diario['eventos']);
        $this->assertSame('neutral', $diario['eventos'][0]['tipo']);
        $this->assertCount(1, $diario['grupos']);
        $this->assertCount(1, $diario['grupos'][0]['eventos']);
        $this->assertSame('neutral', $diario['grupos'][0]['eventos'][0]['tipo']);
    }
}
