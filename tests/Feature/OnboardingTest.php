<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Pokedex;
use App\Models\Pokemon;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    private function createPokemon(int $id, string $name): Pokemon
    {
        return Pokemon::create([
            'id' => $id,
            'name' => $name,
            'species_id' => $id,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);
    }

    private function seedEquipoPokemons(): void
    {
        // Equipo A: 69 Bellsprout, 37 Vulpix, 79 Slowpoke
        $this->createPokemon(69, 'Bellsprout');
        $this->createPokemon(37, 'Vulpix');
        $this->createPokemon(79, 'Slowpoke');
        // Equipo B: 183 Marill, 228 Houndour, 220 Swinub
        $this->createPokemon(183, 'Marill');
        $this->createPokemon(228, 'Houndour');
        $this->createPokemon(220, 'Swinub');
        // Equipo C: 341 Corphish, 328 Trapinch, 285 Shroomish
        $this->createPokemon(341, 'Corphish');
        $this->createPokemon(328, 'Trapinch');
        $this->createPokemon(285, 'Shroomish');
    }

    public function test_get_onboarding_sin_equipo_devuelve_200_y_los_3_equipos(): void
    {
        $this->seedEquipoPokemons();
        $this->actingAsUser();

        $response = $this->get('/onboarding/equipo-inicial');

        $response->assertOk();
        $equipos = $response->viewData('equipos');

        $this->assertCount(3, $equipos);
        $this->assertSame(['A', 'B', 'C'], array_column($equipos, 'key'));
        $this->assertSame('Equipo A', $equipos[0]['nombre']);
        $this->assertSame('Equipo B', $equipos[1]['nombre']);
        $this->assertSame('Equipo C', $equipos[2]['nombre']);
        $this->assertSame([69, 37, 79], $equipos[0]['pokemon_ids']);
        $this->assertSame([183, 228, 220], $equipos[1]['pokemon_ids']);
        $this->assertSame([341, 328, 285], $equipos[2]['pokemon_ids']);
        // Nombres resueltos desde la tabla pokemon.
        $this->assertSame(['Bellsprout', 'Vulpix', 'Slowpoke'], $equipos[0]['pokemon_nombres']);
    }

    public function test_get_onboarding_resuelve_nombres_con_fallback_si_falta_pokemon(): void
    {
        // Sin seedear pokémon: los nombres deben caer al fallback 'Pokémon #id'.
        $this->actingAsUser();

        $response = $this->get('/onboarding/equipo-inicial');

        $response->assertOk();
        $equipos = $response->viewData('equipos');
        $this->assertSame(['Pokémon #69', 'Pokémon #37', 'Pokémon #79'], $equipos[0]['pokemon_nombres']);
    }

    public function test_get_onboarding_con_equipo_existente_redirige_a_home(): void
    {
        $user = $this->actingAsUser();
        Team::create(['name' => 'Ya tengo equipo', 'user_id' => $user->id]);

        $response = $this->get('/onboarding/equipo-inicial');

        $response->assertRedirect('/');
    }

    public function test_post_valido_crea_reclutados_team_y_members(): void
    {
        $this->seedEquipoPokemons();
        $user = $this->actingAsUser();

        $response = $this->post('/onboarding/equipo-inicial', ['team_key' => 'A']);

        $response->assertRedirect('/');
        $response->assertSessionHas('success', 'Equipo inicial creado correctamente');

        $this->assertDatabaseHas('teams', ['name' => 'Equipo A', 'user_id' => $user->id]);
        $this->assertDatabaseCount('teams', 1);

        $this->assertSame(3, Reclutado::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('reclutados', [
            'user_id' => $user->id,
            'nombre' => 'Bellsprout',
            'pokemon_id' => 69,
            'es_shiny' => false,
        ]);
        $this->assertDatabaseHas('reclutados', [
            'user_id' => $user->id,
            'nombre' => 'Vulpix',
            'pokemon_id' => 37,
            'es_shiny' => false,
        ]);
        $this->assertDatabaseHas('reclutados', [
            'user_id' => $user->id,
            'nombre' => 'Slowpoke',
            'pokemon_id' => 79,
            'es_shiny' => false,
        ]);

        $team = Team::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(3, TeamMember::where('team_id', $team->id)->count());
        $this->assertDatabaseHas('team_members', ['team_id' => $team->id, 'slot' => 1, 'behavior' => 'VANGUARDIA']);
        $this->assertDatabaseHas('team_members', ['team_id' => $team->id, 'slot' => 2, 'behavior' => 'COMBATIENTE']);
        $this->assertDatabaseHas('team_members', ['team_id' => $team->id, 'slot' => 3, 'behavior' => 'RECOLECTOR']);

        $this->assertDatabaseHas('pokedex', [
            'user_id' => $user->id,
            'pokemon_id' => 69,
            'visto' => true,
            'atrapado' => true,
        ]);
        $this->assertDatabaseHas('pokedex', [
            'user_id' => $user->id,
            'pokemon_id' => 37,
            'visto' => true,
            'atrapado' => true,
        ]);
        $this->assertDatabaseHas('pokedex', [
            'user_id' => $user->id,
            'pokemon_id' => 79,
            'visto' => true,
            'atrapado' => true,
        ]);
    }

    public function test_post_equipo_b_usa_rastreador_en_slot_3(): void
    {
        $this->seedEquipoPokemons();
        $user = $this->actingAsUser();

        $this->post('/onboarding/equipo-inicial', ['team_key' => 'B']);

        $this->assertDatabaseHas('teams', ['name' => 'Equipo B', 'user_id' => $user->id]);
        $team = Team::where('user_id', $user->id)->firstOrFail();
        $this->assertDatabaseHas('team_members', ['team_id' => $team->id, 'slot' => 3, 'behavior' => 'RASTREADOR']);
    }

    public function test_post_team_key_invalido_devuelve_error_de_validacion(): void
    {
        $this->actingAsUser();

        $response = $this->post('/onboarding/equipo-inicial', ['team_key' => 'X']);

        $response->assertSessionHasErrors('team_key');
        $this->assertDatabaseCount('teams', 0);
        $this->assertDatabaseCount('reclutados', 0);
    }

    public function test_post_sin_team_key_devuelve_error_de_validacion(): void
    {
        $this->actingAsUser();

        $response = $this->post('/onboarding/equipo-inicial', []);

        $response->assertSessionHasErrors('team_key');
    }

    public function test_post_usuario_ya_con_equipo_no_crea_nada(): void
    {
        $this->seedEquipoPokemons();
        $user = $this->actingAsUser();
        Team::create(['name' => 'Existente', 'user_id' => $user->id]);

        $response = $this->post('/onboarding/equipo-inicial', ['team_key' => 'A']);

        $response->assertRedirect();
        $this->assertDatabaseCount('teams', 1);
        $this->assertDatabaseCount('reclutados', 0);
        $this->assertDatabaseCount('team_members', 0);
    }

    public function test_post_valido_asigna_todo_al_usuario_autenticado(): void
    {
        $this->seedEquipoPokemons();
        $user = $this->actingAsUser();
        // Otro usuario ya registrado no debe verse afectado.
        $other = User::factory()->create();

        $this->post('/onboarding/equipo-inicial', ['team_key' => 'C']);

        $this->assertSame(0, Reclutado::where('user_id', $other->id)->count());
        $this->assertSame(0, Team::where('user_id', $other->id)->count());
        $this->assertSame(0, Pokedex::where('user_id', $other->id)->count());
        $this->assertSame(1, Team::where('user_id', $user->id)->count());
        $team = Team::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(3, TeamMember::where('team_id', $team->id)->count());
    }

    public function test_post_valido_marca_pokemon_como_atrapados_en_pokedex(): void
    {
        $this->seedEquipoPokemons();
        $user = $this->actingAsUser();

        $this->post('/onboarding/equipo-inicial', ['team_key' => 'C']);

        $this->assertDatabaseHas('pokedex', [
            'user_id' => $user->id,
            'pokemon_id' => 341,
            'visto' => true,
            'atrapado' => true,
        ]);
        $this->assertDatabaseHas('pokedex', [
            'user_id' => $user->id,
            'pokemon_id' => 328,
            'visto' => true,
            'atrapado' => true,
        ]);
        $this->assertDatabaseHas('pokedex', [
            'user_id' => $user->id,
            'pokemon_id' => 285,
            'visto' => true,
            'atrapado' => true,
        ]);
        $this->assertDatabaseCount('pokedex', 3);
    }

    public function test_registro_completo_redirige_a_onboarding_y_post_crea_datos(): void
    {
        $this->seedEquipoPokemons();

        $response = $this->post('/register', [
            'name' => 'nuevo_entrenador',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertRedirect(route('onboarding.equipo-inicial'));
        $this->assertAuthenticated();

        $user = User::where('name', 'nuevo_entrenador')->firstOrFail();
        $this->assertSame(0, Team::where('user_id', $user->id)->count());

        $this->get('/onboarding/equipo-inicial')->assertOk();

        $this->post('/onboarding/equipo-inicial', ['team_key' => 'A'])->assertRedirect('/');

        $this->assertSame(1, Team::where('user_id', $user->id)->count());
        $this->assertSame(3, Reclutado::where('user_id', $user->id)->count());
        $this->assertSame(3, TeamMember::whereHas('team', fn ($q) => $q->where('user_id', $user->id))->count());
    }
}
