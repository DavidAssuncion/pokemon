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
     * La vista /equipos (Favoritos) renderiza el componente Alpine con el modal
     * de detalle y los controles de favoritos.
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
        $response->assertSee('favoritosApp', false);
        // Botón ojo en el grid de disponibles
        $response->assertSee('openDetail(pokemon)', false);
        // Modal de detalle
        $response->assertSee('showDetailModal', false);
        $response->assertSee('detail-modal-title', false);
        $response->assertSee('nivelDe(detailPokemon)', false);
        // Acciones (fetch POST evolucionar + DELETE liberar)
        $response->assertSee("'/reclutado/' + pokemon.id + '/evolucionar'", false);
        $response->assertSee("method: 'DELETE'", false);
        // Guardas de exploración
        $response->assertSee('enExploracion(detailPokemon.id)', false);
        // Favoritos: toggle y gestión
        $response->assertSee('toggleFavorito(pokemon)', false);
        $response->assertSee('Gestionar favoritos', false);
        // Exploración individual
        $response->assertSee('/api/exploraciones/store-individual', false);
        $response->assertSee('/api/reclutados/', false);
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
        $response->assertSee("'/images/iconos_webp/' + detailPokemon.pokemon_id + '.webp'", false);
    }

    /**
     * El modal de detalle consume el contrato de opciones de evolución:
     * fetch de /evoluciones, selector de destino, barras de exp y botón de caramelo.
     */
    public function test_equipos_detail_modal_evolution_markers_are_present(): void
    {
        $response = $this->get('/equipos');

        $response->assertOk();
        // Estados Alpine de evolución
        $response->assertSee('detailEvoluciones', false);
        $response->assertSee('detailEvolucionesLoading', false);
        $response->assertSee('selectedEvolucionId', false);
        // Fetch de opciones de evolución al abrir el modal
        $response->assertSee('cargarEvoluciones(pokemon)', false);
        $response->assertSee("'/reclutado/' + pokemon.id + '/evoluciones'", false);
        // Selector de destino (solo si hay varias opciones)
        $response->assertSee('Selecciona a qué Pokémon evolucionar', false);
        $response->assertSee('selectedEvolucionId === opcion.pokemon_id', false);
        // Opción seleccionada (barras de exp hacia la evolución)
        $response->assertSee('opcionSeleccionada()', false);
        $response->assertSee("formatExp(req.actual) + ' / ' + formatExp(req.necesario)", false);
        // Botón de caramelo con guardas
        $response->assertSee('alimentarCaramelo(req)', false);
        $response->assertSee('puedeAlimentar(req)', false);
        $response->assertSee("'/images/candy_type/' + req.slug + '.webp'", false);
        $response->assertSee("'/images/candy_pokemon/0.webp'", false);
        // POST de caramelo con destino seleccionado
        $response->assertSee("'/reclutado/' + pokemon.id + '/dar-caramelo'", false);
        // Evolucionar envía el destino seleccionado
        $response->assertSee('evolved_species_id: this.selectedEvolucionId', false);
        // Sin evolución / error silencioso
        $response->assertSee('Este Pokémon no tiene evolución.', false);
        $response->assertSee('No hay información de evolución disponible.', false);
    }
}