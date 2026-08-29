<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
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

    /**
     * Con un usuario autenticado el header muestra su nombre, el nivel
     * (del usuario autenticado) y el formulario de logout POST a /logout.
     */
    public function test_layout_renders_authenticated_user_name_and_logout(): void
    {
        // Usuario NO persistido: el layout solo lee auth()->user()->name, no requiere BD
        // (evita depender de la BD de test compartida con los backends en paralelo).
        $user = new User(['name' => 'Ash Ketchum']);
        $user->id = 99;

        $view = $this->actingAs($user)->view('layouts.app', [
            'nivelJugador' => 12,
            'progresoNivel' => 30,
        ]);

        $view->assertSee('Ash Ketchum', false);
        $view->assertSee('Nv 12', false);
        $view->assertSee('style="width: 30%"', false);
        // Logout: form POST con CSRF y botón submit (url() genera URL absoluta en test)
        $view->assertSee('/logout', false);
        $view->assertSee('method="POST"', false);
        $view->assertSee('name="_token"', false);
        $view->assertSee('type="submit"', false);
        $view->assertSee('Salir', false);
    }

    /**
     * Sin usuario autenticado (p. ej. preview) el bloque de usuario
     * (nombre + logout) se oculta; el badge de nivel permanece.
     */
    public function test_layout_guest_hides_user_block(): void
    {
        $view = $this->view('layouts.app', [
            'nivelJugador' => 4,
            'progresoNivel' => 10,
        ]);

        $view->assertSee('Nv 4', false);
        $view->assertDontSee('/logout', false);
        $view->assertDontSee('Salir', false);
    }
}
