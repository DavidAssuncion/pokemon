<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TipoEnum;
use App\Models\Pokemon;
use App\Models\PokemonType;
use App\Models\Reclutado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendEquiposRolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
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

    /**
     * El rol de un miembro se actualiza ahora a nivel de reclutado individual:
     * POST a /api/reclutado/{reclutado_id}/rol con el behavior, usando el rol del
     * reclutado como valor inicial con fallback al legado de team_members.
     */
    public function test_equipos_role_selector_uses_reclutado_rol_endpoint(): void
    {
        $pokemon = $this->createPokemon(1);
        PokemonType::create(['pokemon_id' => $pokemon->id, 'type' => TipoEnum::GRASS, 'slot' => 1]);
        Reclutado::create([
            'user_id' => auth()->id(),
            'nombre' => 'Bulbi',
            'pokemon_id' => $pokemon->id,
            'exp' => ['total' => 100],
            'es_shiny' => false,
            'obj_equipados' => [],
            'movimientos' => [],
            'behavior' => 'COMBATIENTE',
        ]);

        $response = $this->get('/equipos');

        $response->assertOk();
        // Actualización vía endpoint individual de reclutado (RFC behavior)
        $response->assertSee("'/api/reclutado/' + reclutadoId + '/rol'", false);
        $response->assertSee('JSON.stringify({ behavior })', false);
        // Valor inicial: rol del reclutado con fallback al legado team_members.behavior
        $response->assertSee('member.reclutado?.behavior || member.behavior || \'VANGUARDIA\'', false);
        $response->assertSee('rolInicialDe(getTeamMember(team, slot))', false);
        // El endpoint antiguo de team member ya no se usa desde el frontend
        $response->assertDontSee('/teams/update-member-role', false);
        // Los 4 roles siguen disponibles en el selector
        $response->assertSee('value="VANGUARDIA"', false);
        $response->assertSee('value="COMBATIENTE"', false);
        $response->assertSee('value="RECOLECTOR"', false);
        $response->assertSee('value="RASTREADOR"', false);
    }
}
