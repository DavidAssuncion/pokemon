<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Pokemon;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Equipos\Domain\TeamAggregate;
use Src\Equipos\Infra\EloquentTeamRepository;
use Tests\TestCase;

class EloquentTeamRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    private function createPokemon(int $id): Pokemon
    {
        return Pokemon::create([
            'id' => $id,
            'name' => 'pokemon-'.$id,
            'species_id' => $id,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);
    }

    private function createReclutado(int $userId, int $pokemonId, string $nombre): Reclutado
    {
        return Reclutado::create([
            'user_id' => $userId,
            'nombre' => $nombre,
            'pokemon_id' => $pokemonId,
            'exp' => ['exp' => 100],
            'obj_equipados' => [],
            'movimientos' => [],
        ]);
    }

    public function test_obtener_todos_devuelve_team_aggregates_con_user_id_y_miembros(): void
    {
        $user = $this->actingAsUser();
        $pokemon = $this->createPokemon(1);
        $reclutado = $this->createReclutado($user->id, $pokemon->id, 'Bulbi');
        $team = Team::create(['name' => 'Alpha', 'user_id' => $user->id]);
        TeamMember::create([
            'team_id' => $team->id,
            'pokemon_id' => $reclutado->id,
            'slot' => 1,
            'behavior' => 'VANGUARDIA',
        ]);

        $resultado = app(EloquentTeamRepository::class)->obtenerTodos();

        $this->assertCount(1, $resultado);
        $agregado = $resultado[0];
        $this->assertInstanceOf(TeamAggregate::class, $agregado);
        $this->assertSame($team->id, $agregado->id);
        $this->assertSame('Alpha', $agregado->name);
        $this->assertSame($user->id, $agregado->userId);
        $this->assertCount(1, $agregado->members);
        // Deuda conocida: los miembros siguen siendo modelos Eloquent con
        // relaciones cargadas (reclutado → pokemon), como consumen las vistas.
        $this->assertInstanceOf(TeamMember::class, $agregado->members[0]);
        $this->assertSame(1, $agregado->members[0]->slot);
        $this->assertSame('VANGUARDIA', $agregado->members[0]->behavior);
        $this->assertSame($reclutado->id, $agregado->members[0]->reclutado->id);
        $this->assertSame($pokemon->id, $agregado->members[0]->reclutado->pokemon->id);
    }

    public function test_guardar_persiste_user_id_y_miembros_de_un_equipo_existente(): void
    {
        $user = $this->actingAsUser();
        $this->createPokemon(1);
        $this->createPokemon(2);
        $reclutado1 = $this->createReclutado($user->id, 1, 'Bulbi');
        $reclutado2 = $this->createReclutado($user->id, 2, 'Char');
        $team = Team::create(['name' => 'Viejo', 'user_id' => $user->id]);

        $agregado = new TeamAggregate(
            id: $team->id,
            name: 'Nuevo Equipo',
            userId: $user->id,
            members: [
                new TeamMember(['pokemon_id' => $reclutado1->id, 'slot' => 1, 'behavior' => 'VANGUARDIA']),
                new TeamMember(['pokemon_id' => $reclutado2->id, 'slot' => 2, 'behavior' => 'COMBATIENTE']),
            ],
        );

        app(EloquentTeamRepository::class)->guardar($agregado);

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'name' => 'Nuevo Equipo', 'user_id' => $user->id]);
        $this->assertDatabaseHas('team_members', [
            'team_id' => $team->id,
            'pokemon_id' => $reclutado1->id,
            'slot' => 1,
            'behavior' => 'VANGUARDIA',
        ]);
        $this->assertDatabaseHas('team_members', [
            'team_id' => $team->id,
            'pokemon_id' => $reclutado2->id,
            'slot' => 2,
            'behavior' => 'COMBATIENTE',
        ]);
    }

    public function test_guardar_actualiza_miembro_existente_por_pokemon_id(): void
    {
        $user = $this->actingAsUser();
        $this->createPokemon(1);
        $reclutado = $this->createReclutado($user->id, 1, 'Bulbi');
        $team = Team::create(['name' => 'Alpha', 'user_id' => $user->id]);
        TeamMember::create([
            'team_id' => $team->id,
            'pokemon_id' => $reclutado->id,
            'slot' => 1,
            'behavior' => 'VANGUARDIA',
        ]);

        $agregado = new TeamAggregate(
            id: $team->id,
            name: 'Alpha',
            userId: $user->id,
            members: [
                new TeamMember(['pokemon_id' => $reclutado->id, 'slot' => 3, 'behavior' => 'RASTREADOR']),
            ],
        );

        app(EloquentTeamRepository::class)->guardar($agregado);

        $this->assertDatabaseCount('team_members', 1);
        $this->assertDatabaseHas('team_members', [
            'team_id' => $team->id,
            'pokemon_id' => $reclutado->id,
            'slot' => 3,
            'behavior' => 'RASTREADOR',
        ]);
    }

    public function test_guardar_elimina_miembros_que_ya_no_estan_en_el_agregado(): void
    {
        $user = $this->actingAsUser();
        $this->createPokemon(1);
        $this->createPokemon(2);
        $reclutado1 = $this->createReclutado($user->id, 1, 'Bulbi');
        $reclutado2 = $this->createReclutado($user->id, 2, 'Char');
        $team = Team::create(['name' => 'Alpha', 'user_id' => $user->id]);
        TeamMember::create(['team_id' => $team->id, 'pokemon_id' => $reclutado1->id, 'slot' => 1, 'behavior' => 'VANGUARDIA']);
        TeamMember::create(['team_id' => $team->id, 'pokemon_id' => $reclutado2->id, 'slot' => 2, 'behavior' => 'COMBATIENTE']);

        $agregado = new TeamAggregate(
            id: $team->id,
            name: 'Alpha',
            userId: $user->id,
            members: [
                new TeamMember(['pokemon_id' => $reclutado1->id, 'slot' => 1, 'behavior' => 'VANGUARDIA']),
            ],
        );

        app(EloquentTeamRepository::class)->guardar($agregado);

        $this->assertDatabaseCount('team_members', 1);
        $this->assertDatabaseHas('team_members', ['team_id' => $team->id, 'pokemon_id' => $reclutado1->id]);
        $this->assertDatabaseMissing('team_members', ['team_id' => $team->id, 'pokemon_id' => $reclutado2->id]);
    }

    public function test_guardar_con_miembros_vacios_borra_todos_los_miembros(): void
    {
        $user = $this->actingAsUser();
        $this->createPokemon(1);
        $this->createPokemon(2);
        $reclutado1 = $this->createReclutado($user->id, 1, 'Bulbi');
        $reclutado2 = $this->createReclutado($user->id, 2, 'Char');
        $team = Team::create(['name' => 'Alpha', 'user_id' => $user->id]);
        TeamMember::create(['team_id' => $team->id, 'pokemon_id' => $reclutado1->id, 'slot' => 1, 'behavior' => 'VANGUARDIA']);
        TeamMember::create(['team_id' => $team->id, 'pokemon_id' => $reclutado2->id, 'slot' => 2, 'behavior' => 'COMBATIENTE']);

        $agregado = new TeamAggregate(
            id: $team->id,
            name: 'Alpha',
            userId: $user->id,
            members: [],
        );

        app(EloquentTeamRepository::class)->guardar($agregado);

        $this->assertDatabaseCount('team_members', 0);
        $this->assertDatabaseHas('teams', ['id' => $team->id, 'name' => 'Alpha', 'user_id' => $user->id]);
    }
}
