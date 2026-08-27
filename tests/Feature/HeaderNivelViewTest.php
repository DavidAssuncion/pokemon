<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class HeaderNivelViewTest extends TestCase
{
    /**
     * El layout renderiza el badge de nivel y la barra de progreso
     * con las variables compartidas por View::share.
     */
    public function test_layout_renders_level_badge_and_progress_bar(): void
    {
        $view = $this->view('layouts.app', [
            'nivelJugador' => 7,
            'progresoNivel' => 45,
        ]);

        $view->assertSee('Nv 7', false);
        $view->assertSee('title="Nivel 7"', false);
        $view->assertSee('style="width: 45%"', false);
    }

    /**
     * Sin las variables compartidas (render fuera del share) la vista
     * no revienta y usa los valores por defecto.
     */
    public function test_layout_falls_back_without_shared_variables(): void
    {
        $view = $this->view('layouts.app');

        $view->assertSee('Nv 1', false);
        $view->assertSee('title="Nivel 1"', false);
        $view->assertSee('style="width: 0%"', false);
    }

    /**
     * Los límites de progreso 0 y 100 se reflejan en el ancho del fill.
     */
    public function test_layout_renders_progress_boundaries(): void
    {
        $viewZero = $this->view('layouts.app', [
            'nivelJugador' => 3,
            'progresoNivel' => 0,
        ]);
        $viewZero->assertSee('style="width: 0%"', false);

        $viewFull = $this->view('layouts.app', [
            'nivelJugador' => 3,
            'progresoNivel' => 100,
        ]);
        $viewFull->assertSee('style="width: 100%"', false);
    }
}
