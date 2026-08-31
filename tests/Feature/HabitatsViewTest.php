<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Collection;
use Tests\TestCase;

class HabitatsViewTest extends TestCase
{
    /**
     * El modal de exploración del detalle de hábitat renderiza el panel de
     * preparación de la expedición (preview) con fetch a /exploraciones/preview
     * y el botón "Enviar expedición" deshabilitado hasta cargar el preview.
     */
    public function test_habitat_show_renders_expedition_preview_panel(): void
    {
        $reclutado = (object) ['pokemon_id' => 25, 'nombre' => 'Pikachu'];
        $miembro = (object) ['reclutado' => $reclutado];
        $equipo = (object) ['id' => 1, 'name' => 'Equipo A', 'members' => [$miembro, null, null]];

        $view = $this->view('habitats.show', [
            'habitat' => [
                'id' => 1,
                'name' => 'Bosque',
                'image' => '',
                'min_lvl_1' => 1,
                'min_lvl_2' => null,
                'min_lvl_3' => null,
                'levels' => [1 => [], 2 => [], 3 => []],
            ],
            'teams' => new Collection([$equipo]),
            'exploracionesActivas' => new Collection(),
            'equiposEnExploracion' => new Collection(),
            'sightedPokemonIds' => [],
            'nivelJugador' => 5,
        ]);

        // Modal de exploración + panel de preparación
        $view->assertSee('Confirmar Exploración', false);
        $view->assertSee('/exploraciones/preview', false);
        $view->assertSee('previewLoading', false);
        $view->assertSee('Peligro de la zona', false);
        $view->assertSee('Afinidad del equipo', false);
        $view->assertSee('Equipo bien preparado para esta zona', false);
        $view->assertSee('starRating', false);
        $view->assertSee('riesgoClass', false);
        // Sección "Tipos frente a la zona" (matchups) + defensividad ante contrato ausente
        $view->assertSee('Tipos frente a la zona', false);
        $view->assertSee('preview.matchups && preview.matchups.length', false);
        $view->assertSee('mu.miembro_tipos.join', false);
        $view->assertSee('mu.pool_tipo', false);
        $view->assertSee("mu.clasificacion === 'severo'", false);
        // Botón renombrado y con estado de carga
        $view->assertSee('Enviar expedición', false);
        $view->assertSee(':disabled="!previewLoaded"', false);
        $view->assertDontSee('>Explorar<', false);
    }
}
