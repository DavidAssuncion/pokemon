<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReclutadoRolEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usuario = User::factory()->create(['experiencia' => 10 * 10 ** 3]);
        $this->actingAs($this->usuario);
    }

    private function crearPokemon(int $id): Pokemon
    {
        $pokemon = Pokemon::create([
            'id' => $id,
            'name' => 'pokemon-'.$id,
            'species_id' => $id,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
            'evolution_chain_id' => 50 + $id,
        ]);

        $mapa = [
            'hp' => StatEnum::HP,
            'atk' => StatEnum::ATTACK,
            'def' => StatEnum::DEFENSE,
            'spAtk' => StatEnum::SPECIAL_ATTACK,
            'spDef' => StatEnum::SPECIAL_DEFENSE,
            'speed' => StatEnum::SPEED,
        ];
        foreach ($mapa as $clave => $stat) {
            PokemonStat::create([
                'pokemon_id' => $pokemon->id,
                'stat' => $stat,
                'base_stat' => 50,
                'effort' => 0,
            ]);
        }

        PokemonType::create(['pokemon_id' => $pokemon->id, 'type' => TipoEnum::NORMAL, 'slot' => 1]);

        return $pokemon;
    }

    private function crearReclutado(int $pokemonId, string $nombre = 'Reclutado'): Reclutado
    {
        return Reclutado::create([
            'user_id' => $this->usuario->id,
            'pokemon_id' => $pokemonId,
            'nombre' => $nombre,
            'exp' => ['total' => 10 * 5 ** 3],
            'es_shiny' => false,
            'obj_equipados' => [],
            'movimientos' => [],
        ]);
    }

    #[Test]
    public function test_actualizar_rol_cambia_behavior_del_reclutado(): void
    {
        $pokemon = $this->crearPokemon(1);
        $reclutado = $this->crearReclutado($pokemon->id);

        $response = $this->postJson("/api/reclutado/{$reclutado->id}/rol", ['behavior' => 'VANGUARDIA']);

        $response->assertOk()->assertJson(['behavior' => 'VANGUARDIA']);

        $this->assertDatabaseHas('reclutados', ['id' => $reclutado->id, 'behavior' => 'VANGUARDIA']);
    }

    #[Test]
    public function test_actualizar_rol_validacion_enum(): void
    {
        $pokemon = $this->crearPokemon(1);
        $reclutado = $this->crearReclutado($pokemon->id);

        $this->postJson("/api/reclutado/{$reclutado->id}/rol", ['behavior' => 'SOPORTE'])
            ->assertStatus(422);
    }

    #[Test]
    public function test_actualizar_rol_sincroniza_team_members_behavior(): void
    {
        $pokemon = $this->crearPokemon(1);
        $reclutado = $this->crearReclutado($pokemon->id);

        // El reclutado pertenece a un equipo con behavior legacy COMBATIENTE.
        $team = Team::create(['name' => 'Equipo', 'user_id' => $this->usuario->id]);
        $member = TeamMember::create([
            'team_id' => $team->id,
            'pokemon_id' => $reclutado->id,
            'slot' => 1,
            'behavior' => 'COMBATIENTE',
        ]);

        $this->postJson("/api/reclutado/{$reclutado->id}/rol", ['behavior' => 'RASTREADOR'])
            ->assertOk();

        // Se sincroniza team_members.behavior (mismo pokemon_id).
        $this->assertDatabaseHas('team_members', ['id' => $member->id, 'behavior' => 'RASTREADOR']);
        $this->assertDatabaseHas('reclutados', ['id' => $reclutado->id, 'behavior' => 'RASTREADOR']);
    }

    #[Test]
    public function test_actualizar_rol_reclutado_ajeno_404(): void
    {
        $otro = User::factory()->create();
        $pokemon = $this->crearPokemon(1);
        $reclutado = Reclutado::create([
            'id' => 2,
            'user_id' => $otro->id,
            'pokemon_id' => $pokemon->id,
            'nombre' => 'Ajeno',
            'exp' => ['total' => 0],
        ]);

        $this->postJson("/api/reclutado/{$reclutado->id}/rol", ['behavior' => 'VANGUARDIA'])
            ->assertNotFound();
    }
}
