<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Tests de render de las vistas de autenticación (sin rutas HTTP: las
 * rutas /login y /register las implementa el backend en paralelo).
 */
class AuthViewsTest extends TestCase
{
    public function test_login_view_renders_form_and_register_link(): void
    {
        $view = $this->view('auth.login');

        // url() genera URL absoluta (http://localhost/login) en render directo
        $view->assertSee('/login', false);
        $view->assertSee('method="POST"', false);
        $view->assertSee('name="_token"', false);
        $view->assertSee('name="name"', false);
        $view->assertSee('name="password"', false);
        $view->assertSee('autocomplete="username"', false);
        $view->assertSee('autocomplete="current-password"', false);
        $view->assertSee('/register', false);
        $view->assertSee('Regístrate', false);
    }

    public function test_register_view_renders_form_and_login_link(): void
    {
        $view = $this->view('auth.register');

        $view->assertSee('/register', false);
        $view->assertSee('method="POST"', false);
        $view->assertSee('name="name"', false);
        $view->assertSee('name="password"', false);
        $view->assertSee('name="password_confirmation"', false);
        $view->assertSee('autocomplete="new-password"', false);
        $view->assertSee('/login', false);
        $view->assertSee('Inicia sesión', false);
    }

    public function test_login_view_displays_validation_errors(): void
    {
        $this->withViewErrors([
            'name' => 'El nombre es obligatorio.',
            'password' => 'La contraseña es incorrecta.',
        ]);

        $view = $this->view('auth.login');

        $view->assertSee('El nombre es obligatorio.', false);
        $view->assertSee('La contraseña es incorrecta.', false);
    }

    public function test_register_view_preserves_old_name_input(): void
    {
        $this->withViewErrors(['name' => 'Ese nombre ya está en uso.']);

        // En render directo el request no tiene sesión: se la asociamos para
        // que old('name') lea el _old_input (como haría StartSession por HTTP).
        $session = app('session')->driver();
        app('request')->setLaravelSession($session);
        $session->put('_old_input', ['name' => 'Ash']);

        $view = $this->view('auth.register');

        $view->assertSee('Ese nombre ya está en uso.', false);
        $view->assertSee('value="Ash"', false);
    }

    public function test_login_view_renders_flash_status_message(): void
    {
        session(['status' => 'Sesión cerrada correctamente.']);

        $view = $this->view('auth.login');

        $view->assertSee('Sesión cerrada correctamente.', false);
    }
}
