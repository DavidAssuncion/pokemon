<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Collection;
use Tests\TestCase;

class FrontendHabitatModalTest extends TestCase
{
    private function data(array $overrides = []): array
    {
        return array_merge([
            'habitat' => [
                'id' => 1,
                'name' => 'Bosque',
                'image' => '',
                'min_lvl_1' => 1,
                'min_lvl_2' => null,
                'min_lvl_3' => null,
                'levels' => [1 => [], 2 => [], 3 => []],
            ],
            'teams' => new Collection(),
            'exploracionesActivas' => new Collection(),
            'equiposEnExploracion' => new Collection(),
            'sightedPokemonIds' => [],
            'nivelJugador' => 5,
        ], $overrides);
    }

    /**
     * El modal de exploración individual ya no ofrece modo "Indefinido" pero sí
     * recarga el preview al cambiar la duración pasando duracion_horas o return_time.
     */
    public function test_habitat_modal_removes_indefinite_and_reloads_preview_with_duration(): void
    {
        $view = $this->view('habitats.show', $this->data());

        // Sin modo indefinido (radio eliminado + sin branch en confirmExploration ni loadPreview)
        $view->assertDontSee('value="indefinite"', false);
        $view->assertDontSee('data.indefinido = true', false);
        $view->assertDontSee('body.indefinido = true', false);

        // Recarga del preview al cambiar duración: @change en raddios/inputs
        $view->assertSee('@change="loadPreview()"', false);
        // Query de preview con duración
        $view->assertSee("params.set('duracion_horas'", false);
        $view->assertSee("params.set('return_time'", false);
        // El envío ya NO manda indefinido, solo duracion_horas/return_time
        $view->assertSee('data.duracion_horas = this.durationHours', false);
        $view->assertSee('data.return_time = this.returnTime', false);
    }

    /**
     * El panel de preparación expone las recompensas esperadas ("Ganarás esto")
     * y el rol individual sugerido, tolerante a que el backend aún no las devuelva.
     */
    public function test_habitat_modal_renders_expected_rewards_and_suggested_role(): void
    {
        $view = $this->view('habitats.show', $this->data());

        // Sección de recompensas esperadas (x-show tolerante)
        $view->assertSee('Ganarás esto', false);
        $view->assertSee('preview?.recompensas_esperadas', false);
        $view->assertSee('preview.recompensas_esperadas?.por_horas', false);
        $view->assertSee('preview.recompensas_esperadas?.items?.length', false);
        $view->assertSee("'entre ' + item.min + ' y ' + item.max", false);
        $view->assertSee('preview.recompensas_esperadas?.aviso', false);
        // Badge de rol del reclutado (rol || rol_sugerido)
        $view->assertSee('preview.rol || preview.rol_sugerido', false);
        $view->assertSee('preview.rol_sugerido', false);
    }
}
