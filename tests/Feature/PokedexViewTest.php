<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TipoEnum;
use App\Models\Pokemon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PokedexViewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * La vista Pokédex renderiza el componente asíncrono (datagrid) sin errores
     * y mantiene los marcadores clave del rework: pestaña inicial "Vistos",
     * sentinel de scroll infinito, lazy loading y contrato de la API.
     */
    public function test_pokedex_renders_async_datagrid_view(): void
    {
        Pokemon::create([
            'id' => 1,
            'name' => 'bulbasaur',
            'species_id' => 1,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);

        $response = $this->get('/pokedex');

        $response->assertOk();
        $response->assertSee('pokedexApp', false);
        $response->assertSee('x-ref="sentinel"', false);
        $response->assertSee('decoding="async"', false);
        $response->assertSee('loading="lazy"', false);
        // Pestaña inicial por defecto: Vistos (server-side filter)
        $response->assertSee("activeFilter: 'vistos'", false);
        $response->assertSee("params.set('filter[visto]', '1')", false);
        $response->assertSee('filter[atrapado]', false);
        $response->assertSee('/datagrid/pokemon', false);
    }

    /**
     * El filtro de esfuerzo (EVs) renderiza su dropdown con las 6 stats
     * y se envía al datagrid como filter[effort].
     */
    public function test_pokedex_renders_effort_filter(): void
    {
        Pokemon::create([
            'id' => 1,
            'name' => 'bulbasaur',
            'species_id' => 1,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);

        $response = $this->get('/pokedex');

        $response->assertOk();
        // Botón del filtro de esfuerzo
        $response->assertSee("effortFilter || 'Esfuerzo'", false);
        // Las 6 opciones de stat (labels en español) desde StatEnum::options()
        $response->assertSee('PS (HP)', false);
        $response->assertSee('Ataque', false);
        $response->assertSee('Defensa', false);
        $response->assertSee('Ataque Especial', false);
        $response->assertSee('Defensa Especial', false);
        $response->assertSee('Velocidad', false);
        // La lógica de params envía filter[effort]
        $response->assertSee("params.set('filter[effort]', this.effortFilter)", false);
        $response->assertSee('selectEffort(', false);
        $response->assertSee('clearEffortFilter()', false);
    }

    /**
     * Los Pokémon no vistos no descargan su icono: la card usa placeholder CSS
     * y el modal no lanza fetch de detalle para no avistados.
     */
    public function test_pokedex_renders_css_placeholder_for_unseen_pokemon(): void
    {
        Pokemon::create([
            'id' => 25,
            'name' => 'pikachu',
            'species_id' => 25,
            'capture_rate' => 190,
            'base_experience' => 112,
            'height' => 4,
            'weight' => 60,
        ]);

        $response = $this->get('/pokedex');

        $response->assertOk();
        $response->assertSee('Este Pokémon aún no ha sido avistado.', false);
        // El placeholder es CSS (gradiente), no <img> con icono del no visto
        $response->assertSee('bg-linear-to-br from-gray-200 to-gray-300', false);
    }

    /**
     * La vista es compatible con el contrato final del backend:
     * $pokemons = { data, meta }, $counts y $tipos como viewData.
     */
    public function test_pokedex_renders_with_new_backend_contract(): void
    {
        $pokemons = [
            'data' => [
                ['id' => 1, 'name' => 'bulbasaur', 'visto' => true, 'atrapado' => true, 'types' => ['Planta'], 'icon' => '/images/iconos_webp/1.webp'],
                ['id' => 2, 'name' => 'ivysaur', 'visto' => false, 'atrapado' => false, 'types' => ['Planta'], 'icon' => '/images/iconos_webp/2.webp'],
                ['id' => 3, 'name' => 'venusaur', 'visto' => false, 'atrapado' => false, 'types' => ['Planta'], 'icon' => '/images/iconos_webp/3.webp'],
            ],
            'meta' => [
                'total' => 3,
                'page' => 1,
                'per_page' => 100,
                'last_page' => 1,
                'counts' => ['total' => 3, 'vistos' => 1, 'atrapados' => 1, 'no_vistos' => 2],
            ],
        ];

        $view = $this->view('pokedex.index', [
            'pokemons' => $pokemons,
            'counts' => $pokemons['meta']['counts'],
            'tipos' => TipoEnum::options(),
        ]);

        $view->assertSee('pokedexApp', false);
        // El dropdown de tipos consume $tipos del controlador
        $view->assertSee('Eléctrico', false);
        // Los contadores del header vienen de meta.counts
        $view->assertSee('counts.vistos', false);
        // Fallback de icono EXPLÍCITO a la carpeta PNG: los PNG viven en /images/iconos/,
        // nunca se deriva la ruta desde el webp (/images/iconos_webp/{id}.png -> 404)
        $view->assertSee("'/images/iconos/' + pokemon.id + '.png'", false);
        $view->assertDontSee("'/images/iconos_webp/' + pokemon.id + '.png'", false);
    }
}
