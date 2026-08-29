<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PlayerInventory;
use App\Models\Pokedex;
use App\Models\Pokemon;
use App\Models\Reclutable;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_usuario_no_ve_filas_de_otro_usuario(): void
    {
        $usuarioA = User::factory()->create();
        $usuarioB = User::factory()->create();
        $pokemon = $this->createPokemon(1);

        Team::create(['name' => 'Equipo A', 'user_id' => $usuarioA->id]);
        Team::create(['name' => 'Equipo B', 'user_id' => $usuarioB->id]);

        Reclutado::create(['nombre' => 'A1', 'pokemon_id' => $pokemon->id, 'user_id' => $usuarioA->id]);
        Reclutado::create(['nombre' => 'B1', 'pokemon_id' => $pokemon->id, 'user_id' => $usuarioB->id]);

        Reclutable::create(['pokemon_id' => $pokemon->id, 'user_id' => $usuarioA->id, 'cantidad' => 2]);
        Reclutable::create(['pokemon_id' => $pokemon->id, 'user_id' => $usuarioB->id, 'cantidad' => 4]);

        Pokedex::create(['pokemon_id' => $pokemon->id, 'user_id' => $usuarioA->id, 'visto' => true]);
        Pokedex::create(['pokemon_id' => $pokemon->id, 'user_id' => $usuarioB->id, 'visto' => true]);

        PlayerInventory::create(['user_id' => $usuarioA->id, 'item_key' => 'familia:51', 'cantidad' => 5]);
        PlayerInventory::create(['user_id' => $usuarioB->id, 'item_key' => 'familia:51', 'cantidad' => 9]);

        $this->actingAs($usuarioA);

        $this->assertSame(1, Team::count());
        $this->assertSame('Equipo A', Team::first()->name);

        $this->assertSame(1, Reclutado::count());
        $this->assertSame('A1', Reclutado::first()->nombre);

        $this->assertSame(1, Reclutable::count());
        $this->assertSame(2, Reclutable::first()->cantidad);

        $this->assertSame(1, Pokedex::count());

        $this->assertSame(1, PlayerInventory::count());
        $this->assertSame(5, PlayerInventory::first()->cantidad);
    }

    public function test_sin_usuario_autenticado_el_scope_no_filtra(): void
    {
        $usuarioA = User::factory()->create();
        $usuarioB = User::factory()->create();

        Team::create(['name' => 'Equipo A', 'user_id' => $usuarioA->id]);
        Team::create(['name' => 'Equipo B', 'user_id' => $usuarioB->id]);

        $this->assertSame(2, Team::count(), 'sin auth el scope debe quedar inactivo (CLI/jobs)');
    }

    public function test_without_user_scope_ignora_el_filtro_del_usuario(): void
    {
        $usuarioA = User::factory()->create();
        $usuarioB = User::factory()->create();

        Team::create(['name' => 'Equipo A', 'user_id' => $usuarioA->id]);
        Team::create(['name' => 'Equipo B', 'user_id' => $usuarioB->id]);

        $this->actingAs($usuarioA);

        $this->assertSame(1, Team::count());
        $this->assertSame(2, Team::withoutUserScope()->count());
    }

    public function test_la_relacion_user_devuelve_al_propietario(): void
    {
        $usuario = User::factory()->create();
        $team = Team::create(['name' => 'Equipo A', 'user_id' => $usuario->id]);

        $this->assertTrue($team->user->is($usuario));
    }

    private function createPokemon(int $id): Pokemon
    {
        return Pokemon::create([
            'id' => $id,
            'name' => 'Bulbasaur',
            'species_id' => 1,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
            'evolution_chain_id' => 1,
        ]);
    }
}
