<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use PHPUnit\Framework\TestCase;
use Src\Exploraciones\Presentation\EventoExploracionVista;

final class EventoExploracionVistaTest extends TestCase
{
    public function test_descanso_usa_duracion_minutos_y_hp_recuperado(): void
    {
        $vista = EventoExploracionVista::toView([
            'tipo' => 'descanso',
            'duracion_minutos' => 12,
            'hp_recuperado' => 34.0,
        ]);

        $this->assertSame(12, $vista['duracion']);
        $this->assertSame(34.0, $vista['hp_recuperado']);
        $this->assertSame('El explorador descansa y recupera 34 HP', $vista['texto']);
    }

    public function test_descanso_con_nombre_y_recuperacion_decimal(): void
    {
        $vista = EventoExploracionVista::toView([
            'tipo' => 'descanso',
            'explorador_nombre' => 'Ana',
            'duracion_minutos' => 7,
            'hp_recuperado' => 12.5,
        ]);

        $this->assertSame(7, $vista['duracion']);
        $this->assertSame('Ana descansa y recupera 12.5 HP', $vista['texto']);
    }

    public function test_descanso_sin_hp_recuperado_solo_descansa(): void
    {
        $vista = EventoExploracionVista::toView([
            'tipo' => 'descanso',
            'explorador_nombre' => 'Ana',
            'duracion_minutos' => 3,
        ]);

        $this->assertSame('Ana descansa', $vista['texto']);
        $this->assertSame(3, $vista['duracion']);
        $this->assertSame(0.0, $vista['hp_recuperado']);
    }

    public function test_duration_loss_tiene_prioridad_sobre_duracion_minutos(): void
    {
        $vista = EventoExploracionVista::toView([
            'tipo' => 'contratiempo',
            'subtype' => 'terreno',
            'duration_loss' => 9,
            'duracion_minutos' => 20,
        ]);

        $this->assertSame(9, $vista['duracion']);
    }

    public function test_retirada_usa_narrativa_singular_y_nombre(): void
    {
        $vista = EventoExploracionVista::toView([
            'tipo' => 'retirada',
            'explorador_nombre' => 'Ana',
            'reason' => 'grupo_enemigo',
        ]);

        $this->assertSame('Ana se retira: grupo_enemigo', $vista['texto']);
        $this->assertSame('grupo_enemigo', $vista['reason']);
    }

    public function test_grupo_usa_narrativa_singular(): void
    {
        $vista = EventoExploracionVista::toView([
            'tipo' => 'grupo',
            'explorador_nombre' => 'Ana',
        ]);

        $this->assertSame('Ana se encuentra con un grupo salvaje', $vista['texto']);
    }

    public function test_emboscada_usa_el_nombre_del_explorador(): void
    {
        $vista = EventoExploracionVista::toView([
            'tipo' => 'emboscada',
            'explorador_nombre' => 'Ana',
            'resolucion' => 'superada',
            'duration_loss' => 4,
        ]);

        $this->assertSame('¡Emboscada!', $vista['titulo']);
        $this->assertSame('Ana repele el ataque (-4 min)', $vista['subtitulo']);
    }

    public function test_emboscada_escapada_usa_el_nombre_del_explorador(): void
    {
        $vista = EventoExploracionVista::toView([
            'tipo' => 'emboscada',
            'explorador_nombre' => 'Ana',
            'resolucion' => 'huida',
        ]);

        $this->assertSame('Ana escapa perdiendo tiempo', $vista['subtitulo']);
    }

    public function test_contratiempo_desorientacion_usa_el_nombre(): void
    {
        $vista = EventoExploracionVista::toView([
            'tipo' => 'contratiempo',
            'subtype' => 'desorientacion',
            'explorador_nombre' => 'Ana',
            'duration_loss' => 5,
        ]);

        $this->assertSame('Ana pierde el rastro. -5 min', $vista['texto']);
    }

    public function test_derrota_anade_texto_y_reason(): void
    {
        $vista = EventoExploracionVista::toView([
            'tipo' => 'derrota',
            'explorador_nombre' => 'Ana',
            'reason' => 'explorador_debilitado',
        ]);

        $this->assertSame('Ana cae debilitado', $vista['texto']);
        $this->assertSame('explorador_debilitado', $vista['reason']);
        $this->assertSame('se quedó sin PS', $vista['reason_texto']);
    }

    public function test_cancelada_anade_texto_y_motivo(): void
    {
        $vista = EventoExploracionVista::toView([
            'tipo' => 'cancelada',
            'motivo' => 'manual',
        ]);

        $this->assertSame('Expedición cancelada: manual', $vista['texto']);
        $this->assertSame('manual', $vista['motivo']);
    }

    public function test_neutral_es_sin_incidencias(): void
    {
        $vista = EventoExploracionVista::toView(['tipo' => 'neutral']);

        $this->assertSame('Sin incidencias', $vista['texto']);
        $this->assertTrue($vista['_vista']);
    }

    public function test_caramelo_familia_usa_sprite_y_nombre_resuelto(): void
    {
        $vista = EventoExploracionVista::toView([
            'tipo' => 'hallazgo',
            'subtype' => 'caramelo_familia',
            'pokemon_id' => 81,
            'cantidad' => 1,
        ], [81 => 'magnemite']);

        $this->assertSame('magnemite', $vista['candy_nombre']);
        $this->assertSame('/images/iconos_webp/81.webp', $vista['candy_imagen']);
        $this->assertSame('/images/candy_pokemon/81.webp', $vista['candy_src']);
        $this->assertFalse($vista['oculto']);
    }

    public function test_caramelo_familia_sin_nombre_no_se_oculta(): void
    {
        $vista = EventoExploracionVista::toView([
            'tipo' => 'hallazgo',
            'subtype' => 'caramelo_familia',
            'pokemon_id' => 81,
            'cantidad' => 1,
        ]);

        $this->assertNull($vista['candy_nombre']);
        $this->assertSame('/images/iconos_webp/81.webp', $vista['candy_imagen']);
        $this->assertFalse($vista['oculto']);
    }

    public function test_caramelo_tipo_resuelve_tipo_id(): void
    {
        $vista = EventoExploracionVista::toView([
            'tipo' => 'hallazgo',
            'subtype' => 'caramelo_tipo',
            'tipo_id' => 9,
            'cantidad' => 1,
        ]);

        $this->assertSame('Acero', $vista['candy_nombre']);
        $this->assertSame('acero', $vista['candy_slug']);
        $this->assertSame('/images/candy_type/acero.webp', $vista['candy_src']);
        $this->assertSame('Caramelo de tipo Acero', $vista['candy_alt']);
        $this->assertFalse($vista['oculto']);
    }

    public function test_caramelo_tipo_irresoluble_se_oculta(): void
    {
        $vista = EventoExploracionVista::toView([
            'tipo' => 'hallazgo',
            'subtype' => 'caramelo_tipo',
            'tipo_id' => 999,
            'cantidad' => 1,
        ]);

        $this->assertTrue($vista['oculto']);
        $this->assertNull($vista['candy_nombre']);
        $this->assertNull($vista['candy_src']);
    }

    public function test_caramelo_tipo_legacy_por_slug_sigue_funcionando(): void
    {
        $vista = EventoExploracionVista::toView([
            'tipo' => 'hallazgo',
            'subtype' => 'caramelo_tipo',
            'slug' => 'acero',
            'cantidad' => 1,
        ]);

        $this->assertSame('Acero', $vista['candy_nombre']);
        $this->assertSame('acero', $vista['candy_slug']);
        $this->assertSame('/images/candy_type/acero.webp', $vista['candy_src']);
        $this->assertFalse($vista['oculto']);
    }

    public function test_hallazgo_caramelo_ev_completa_stat_con_slugs_reales(): void
    {
        $vista = EventoExploracionVista::toView([
            'tipo' => 'hallazgo',
            'subtype' => 'caramelo_ev',
            'stat' => 4,
            'cantidad' => 1,
        ], [], true);

        $this->assertSame('Ataque Especial', $vista['stat_nombre']);
        $this->assertSame('atksp', $vista['stat_slug']);
        $this->assertSame('/images/candy_ev/atksp.webp', $vista['candy_src']);
        $this->assertNull($vista['candy_nombre_js']);
    }

    public function test_derrota_de_combate_calcula_rondas_y_pv(): void
    {
        $vista = EventoExploracionVista::toView([
            'tipo' => 'encuentro',
            'resolucion' => 'derrota',
            'nombre' => 'bronzor',
            'pokemon_id' => 436,
            'hp_inicial' => 30.0,
            'hp_max' => 39.0,
            'hp_final' => 0.0,
            'rival_hp_inicial' => 34.0,
            'rival_hp_final' => 12.0,
            'rival_hp_max' => 34.0,
            'log' => [
                '--- Ronda 1 ---',
                'bronzor ataca',
                '--- Ronda 2 ---',
                'bronzor ataca',
                '--- Ronda 3 ---',
                'bronzor ataca',
                '--- Ronda 4 ---',
                'bronzor ataca',
            ],
        ]);

        $this->assertSame(4, $vista['rondas']);
        $this->assertSame('bronzor', $vista['rival_nombre']);
        $this->assertSame(
            'Entró con 30/39 PS tras 4 rondas; a bronzor le quedaban 12/34 PS.',
            $vista['detalle_derrota'],
        );
    }

    public function test_derrota_historica_sin_claves_de_pv_no_inventa_detalle(): void
    {
        $vista = EventoExploracionVista::toView([
            'tipo' => 'encuentro',
            'resolucion' => 'derrota',
            'nombre' => 'bronzor',
        ]);

        $this->assertNull($vista['rondas']);
        $this->assertNull($vista['detalle_derrota']);
        $this->assertSame('bronzor', $vista['rival_nombre']);
    }

    public function test_emboscada_perdida_tambien_expone_detalle_de_derrota(): void
    {
        $vista = EventoExploracionVista::toView([
            'tipo' => 'emboscada',
            'resolucion' => 'derrota',
            'explorador_nombre' => 'Ana',
            'nombre' => 'bronzor',
            'hp_inicial' => 25.0,
            'hp_max' => 39.0,
            'hp_final' => 0.0,
            'log' => ['--- Ronda 1 ---', 'bronzor ataca', '--- Ronda 2 ---', 'bronzor ataca'],
        ]);

        $this->assertSame(2, $vista['rondas']);
        $this->assertSame(
            'Entró con 25/39 PS y cayó tras 2 rondas.',
            $vista['detalle_derrota'],
        );
    }

    public function test_emboscada_multipokemon_usa_el_rival_del_subcombate_decisivo(): void
    {
        $vista = EventoExploracionVista::toView([
            'tipo' => 'emboscada',
            'resolucion' => 'derrota',
            'pokemon_ids' => [299, 81],
            'sub_combates' => [
                ['pokemon_id' => 299, 'victoria' => true],
                ['pokemon_id' => 81, 'victoria' => false],
            ],
            'hp_inicial' => 20.0,
            'hp_max' => 39.0,
            'rival_hp_final' => 5.0,
            'rival_hp_max' => 32.0,
            'log' => [
                '--- Ronda 1 ---',
                'magnemite ataca',
                '--- Ronda 2 ---',
                'magnemite ataca',
            ],
        ], [299 => 'nosepass', 81 => 'magnemite']);

        $this->assertSame('magnemite', $vista['rival_nombre']);
        $this->assertStringContainsString(
            'a magnemite le quedaban 5/32 PS',
            (string) $vista['detalle_derrota'],
        );
    }

    public function test_detalle_derrota_usa_singular_con_una_ronda(): void
    {
        $vista = EventoExploracionVista::toView([
            'tipo' => 'encuentro',
            'resolucion' => 'derrota',
            'nombre' => 'magnemite',
            'hp_inicial' => 20.0,
            'hp_max' => 39.0,
            'rival_hp_final' => 5.0,
            'rival_hp_max' => 32.0,
            'log' => ['--- Ronda 1 ---', 'magnemite ataca'],
        ]);

        $this->assertSame(
            'Entró con 20/39 PS tras 1 ronda; a magnemite le quedaban 5/32 PS.',
            $vista['detalle_derrota'],
        );
    }
}
