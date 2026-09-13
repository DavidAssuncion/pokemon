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

    /**
     * El combate de ruta 5v5 es el modo por defecto: panel ruta con nota obligatoria,
     * selector de nivel, preview de rivales y popup de formación con estado "Automática".
     */
    public function test_habitat_defaults_to_ruta_mode_with_ruta_panel(): void
    {
        $view = $this->view('habitats.show', $this->data());

        // Modo por defecto = ruta
        $view->assertSee("modo: 'ruta'", false);
        // Nota visible obligatoria
        $view->assertSee('Combate 5v5 contra pokémon salvajes. Sin límite diario. Al ganar puedes capturar.', false);
        // Panel ruta (badge 5v5 + selector de nivel)
        $view->assertSee('Combate de Ruta', false);
        $view->assertSee('selectRutaNivel(lvl)', false);
        // Fetch de rivales por nivel
        $view->assertSee('/ruta/rivales?nivel=', false);
        // Aviso de pool vacío
        $view->assertSee('No hay Pokémon salvajes disponibles en esta ruta para tu nivel.', false);
        // CTA de ruta
        $view->assertSee('openRutaFormacionPopup()', false);
        // POST iniciar (5v5)
        $view->assertSee('/ruta/iniciar', false);
        // Popup contextual de ruta con chip "Automática" (usa formación persistida)
        $view->assertSee('Combate de Ruta · Configurar Formación', false);
        $view->assertSee("'⚙️ Automática'", false);
        // Error 422 inline en el popup (sin redirigir)
        $view->assertSee('rutaCombatError', false);
        // Botones: sin acceso directo a /equipos (el enlace "Favoritos" se elimina);
        // Exploraciones abre el modo pokémon
        $view->assertDontSee('>Favoritos</span>', false);
        $view->assertSee('toggleExploraciones()', false);
    }

    /**
     * El escalado 5v5 llega a las tarjetas de equipo: grid de 5 slots y, con un
     * equipo sin miembros, se renderizan exactamente 5 placeholders "Vacío".
     */
    public function test_habitat_team_cards_render_five_slots(): void
    {
        $team = (object) [
            'id' => 1,
            'name' => 'Equipo Ruta',
            'members' => [],
        ];

        $testView = $this->view('habitats.show', $this->data([
            'teams' => new Collection([$team]),
        ]));
        $html = (string) $testView;

        $this->assertStringContainsString('grid grid-cols-5', $html);
        $this->assertSame(5, substr_count($html, '>Vacío</p>'));
    }
}
