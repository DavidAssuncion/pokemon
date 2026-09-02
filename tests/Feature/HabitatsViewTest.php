<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Collection;
use Tests\TestCase;

class HabitatsViewTest extends TestCase
{
    /**
     * El modal de exploración individual del detalle de hábitat renderiza el
     * panel de preparación de la expedición (preview individual) con fetch a
     * /exploraciones/preview y el botón "Enviar expedición" deshabilitado
     * hasta cargar el preview.
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

        // Modal de exploración + panel de preparación individual
        $view->assertSee('Confirmar Exploración', false);
        $view->assertSee('/exploraciones/preview', false);
        $view->assertSee('previewLoading', false);
        $view->assertSee('Peligro de la zona', false);
        $view->assertSee('Capacidades del Pokémon', false);
        $view->assertSee('Nivel mínimo requerido', false);
        $view->assertSee('starRating', false);
        $view->assertSee('riesgoClass', false);
        // Capacidades individuales
        $view->assertSee('preview.capacidades', false);
        $view->assertSee('preview.nivel_pokemon', false);
        $view->assertSee('preview.min_lvl', false);
        // Botón renombrado y con estado de carga
        $view->assertSee('Enviar expedición', false);
        $view->assertSee(':disabled="!previewLoaded"', false);
        $view->assertDontSee('>Explorar<', false);
    }
}