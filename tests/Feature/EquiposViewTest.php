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

class EquiposViewTest extends TestCase
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
     * La vista /equipos renderiza el componente Alpine con el modal de detalle:
     * ojo de inspección en ambos overlays, acciones evolucionar/liberar y guardas.
     */
    public function test_equipos_renders_detail_modal_markers(): void
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
        ]);

        $response = $this->get('/equipos');

        $response->assertOk();
        $response->assertSee('equiposApp', false);
        // Botón ojo en ambos overlays (Disponibles y Asignados)
        $response->assertSee('openDetail(pokemon)', false);
        // Modal de detalle
        $response->assertSee('showDetailModal', false);
        $response->assertSee('detail-modal-title', false);
        $response->assertSee('nivelDe(detailPokemon)', false);
        // Acciones (fetch POST evolucionar + DELETE liberar)
        $response->assertSee("'/reclutado/' + pokemon.id + '/evolucionar'", false);
        $response->assertSee("method: 'DELETE'", false);
        // Guardas de exploración
        $response->assertSee('pokemonEnExploracion(pokemon)', false);
        // isInExploration defensivo para lista plana de equipo_id
        $response->assertSee('e.equipo_id === teamId', false);
    }

    /**
     * El tooltip muerto (nunca poblado) se eliminó; el modal de detalle lo sustituye.
     */
    public function test_equipos_has_no_dead_hover_tooltip(): void
    {
        $response = $this->get('/equipos');

        $response->assertOk();
        $response->assertDontSee('hoveredPokemon', false);
        $response->assertDontSee('tooltipX', false);
    }

    /**
     * El modal de detalle consume el payload actual (exp cast con total) sin romper:
     * helpers de nivel/exp presentes y la imagen usa el contrato WebP.
     */
    public function test_equipos_modal_helpers_are_present(): void
    {
        $response = $this->get('/equipos');

        $response->assertOk();
        $response->assertSee('expTotalDe(pokemon)', false);
        $response->assertSee('"\'/images/iconos_webp/\' + detailPokemon.pokemon_id + \'.webp\'"', false);
        $response->assertSee('tipoLabelDel(t)', false);
    }
}
