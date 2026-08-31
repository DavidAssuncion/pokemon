<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_login_page(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_guest_can_view_register_page(): void
    {
        $this->get('/register')->assertOk();
    }

    public function test_registration_creates_user_and_authenticates(): void
    {
        $response = $this->post('/register', [
            'name' => 'ash',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertRedirect(route('onboarding.equipo-inicial'));
        $this->assertDatabaseHas('users', [
            'name' => 'ash',
            'experiencia' => 0,
        ]);
        $this->assertAuthenticated();
    }

    public function test_registration_validates_unique_name_and_confirmed_password(): void
    {
        User::factory()->create(['name' => 'ash']);

        $response = $this->post('/register', [
            'name' => 'ash',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]);

        $response->assertSessionHasErrors(['name', 'password']);
        $this->assertGuest();
    }

    public function test_login_with_valid_credentials_authenticates(): void
    {
        User::factory()->create(['name' => 'misty', 'password' => 'secret123']);

        $response = $this->post('/login', [
            'name' => 'misty',
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs(User::where('name', 'misty')->firstOrFail());
    }

    public function test_login_with_invalid_credentials_fails(): void
    {
        User::factory()->create(['name' => 'misty', 'password' => 'secret123']);

        $response = $this->post('/login', [
            'name' => 'misty',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertGuest();
    }

    public function test_logout_invalidates_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_game_routes_redirect_to_login_when_guest(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/equipos')->assertRedirect('/login');
        $this->get('/pokedex')->assertRedirect('/login');
        $this->get('/datagrid/pokemon')->assertRedirect('/login');
    }

    public function test_login_and_register_pages_redirect_home_when_authenticated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/login')->assertRedirect('/');
        $this->actingAs($user)->get('/register')->assertRedirect('/');
    }
}
