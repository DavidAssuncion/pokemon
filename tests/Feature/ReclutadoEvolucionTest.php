<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TipoEnum;
use App\Jobs\ActualizarPokedexJob;
use App\Models\CarameloTipo;
use App\Models\Pokemon;
use App\Models\PokemonEvolution;
use App\Models\PokemonType;
use App\Models\Reclutado;
use App\Models\ReclutadoExpTipo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Src\Reclutamiento\App\ServicioEvolucion;
use Src\Shared\Domain\NivelHelper;
use Tests\TestCase;

class ReclutadoEvolucionTest extends TestCase
{
    use RefreshDatabase;

    private function crearPokemon(int $id, string $name, int $speciesId): Pokemon
    {
        return Pokemon::create([
            'id' => $id,
            'name' => $name,
            'species_id' => $speciesId,
            'capture_rate' => 45,
            'base_experience' => 62,
            'height' => 6,
            'weight' => 85,
        ]);
    }

    private function crearEvolucion(int $fromSpeciesId, int $evolvedPokemonId, int $minimumLevel): void
    {
        PokemonEvolution::create([
            'evolves_from_species_id' => $fromSpeciesId,
            'evolved_species_id' => $evolvedPokemonId,
            'minimum_level' => $minimumLevel,
        ]);
    }

    private function crearCadenaCharmander(): void
    {
        $this->crearPokemon(4, 'charmander', 4);
        $this->crearPokemon(5, 'charmeleon', 5);
        $this->crearPokemon(6, 'charizard', 6);
        $this->crearPokemon(10034, 'charizard-mega-x', 6);

        $this->crearEvolucion(4, 5, 16);
        $this->crearEvolucion(5, 6, 36);
        // Forma alterna (mega): no debe considerarse la "siguiente evolución"
        $this->crearEvolucion(5, 10034, 40);

        PokemonType::create(['pokemon_id' => 5, 'type' => TipoEnum::FIRE, 'slot' => 1]);
        PokemonType::create(['pokemon_id' => 6, 'type' => TipoEnum::FIRE, 'slot' => 1]);
        PokemonType::create(['pokemon_id' => 6, 'type' => TipoEnum::FLYING, 'slot' => 2]);
    }

    private function crearReclutado(int $pokemonId, int $expTotal, string $nombre = 'Charmander'): Reclutado
    {
        return Reclutado::create([
            'nombre' => $nombre,
            'pokemon_id' => $pokemonId,
            'exp' => ['total' => $expTotal],
            'es_shiny' => false,
            'obj_equipados' => [],
            'movimientos' => [],
        ]);
    }

    public function test_siguiente_evolucion_charmander_a_charmeleon(): void
    {
        $this->crearCadenaCharmander();

        $siguiente = ServicioEvolucion::siguienteEvolucion(Pokemon::findOrFail(4));

        $this->assertNotNull($siguiente);
        $this->assertSame(5, $siguiente->id);
        $this->assertSame('charmeleon', $siguiente->name);
    }

    public function test_siguiente_evolucion_charmeleon_a_charizard(): void
    {
        $this->crearCadenaCharmander();

        $siguiente = ServicioEvolucion::siguienteEvolucion(Pokemon::findOrFail(5));

        $this->assertNotNull($siguiente);
        $this->assertSame(6, $siguiente->id);
        $this->assertSame('charizard', $siguiente->name);
    }

    public function test_siguiente_evolucion_charizard_es_null(): void
    {
        $this->crearCadenaCharmander();

        $this->assertNull(ServicioEvolucion::siguienteEvolucion(Pokemon::findOrFail(6)));
    }

    public function test_umbral_para_nivel_usa_la_curva_x10(): void
    {
        $this->assertSame(70, ServicioEvolucion::umbralParaNivel(1)); // 80 - 10
        $this->assertSame(7210, ServicioEvolucion::umbralParaNivel(15)); // 40960 - 33750
        $this->assertSame(37810, ServicioEvolucion::umbralParaNivel(35)); // 466560 - 428750

        $this->assertSame(
            NivelHelper::experienciaParaNivel(16) - NivelHelper::experienciaParaNivel(15),
            ServicioEvolucion::umbralParaNivel(15)
        );
    }

    public function test_tipos_requeridos_de_la_siguiente_evolucion(): void
    {
        $this->crearCadenaCharmander();

        $this->assertSame(['Fuego'], ServicioEvolucion::tiposRequeridos(Pokemon::findOrFail(5)));
        $this->assertSame(['Fuego', 'Volador'], ServicioEvolucion::tiposRequeridos(Pokemon::findOrFail(6)));
        $this->assertSame([], ServicioEvolucion::tiposRequeridos(null));
    }

    public function test_requisitos_incluye_necesario_actual_caramelos_y_slug(): void
    {
        $this->crearCadenaCharmander();
        $reclutado = $this->crearReclutado(4, 33750); // nivel 15 → umbral 7210
        CarameloTipo::create(['tipo' => 'Fuego', 'cantidad' => 5]);

        $requisitos = ServicioEvolucion::requisitos($reclutado);

        $this->assertCount(1, $requisitos);
        $this->assertSame('Fuego', $requisitos[0]['tipo']);
        $this->assertSame(7210, $requisitos[0]['necesario']);
        $this->assertSame(0, $requisitos[0]['actual']);
        $this->assertSame(5, $requisitos[0]['caramelosDisponibles']);
        $this->assertSame('fuego', $requisitos[0]['slug']);
    }

    public function test_requisitos_con_acentos_genera_slug_ascii(): void
    {
        $this->crearPokemon(25, 'pikachu', 25);
        $this->crearPokemon(26, 'raichu', 26);
        $this->crearEvolucion(25, 26, 22);
        PokemonType::create(['pokemon_id' => 26, 'type' => TipoEnum::ELECTRIC, 'slot' => 1]);
        $reclutado = $this->crearReclutado(25, 0);

        $requisitos = ServicioEvolucion::requisitos($reclutado);

        $this->assertSame('Eléctrico', $requisitos[0]['tipo']);
        $this->assertSame('electrico', $requisitos[0]['slug']);
    }

    public function test_puede_evolucionar_requiere_todos_los_tipos(): void
    {
        $this->crearCadenaCharmander();
        $reclutado = $this->crearReclutado(4, 33750); // nivel 15 → umbral 7210

        $this->assertFalse(ServicioEvolucion::puedeEvolucionar($reclutado));

        ReclutadoExpTipo::create(['reclutado_id' => $reclutado->id, 'tipo' => 'Fuego', 'cantidad' => 7210]);

        $this->assertTrue(ServicioEvolucion::puedeEvolucionar($reclutado->fresh()));
    }

    public function test_puede_evolucionar_con_doble_tipo_requiere_ambos(): void
    {
        $this->crearCadenaCharmander();
        $reclutado = $this->crearReclutado(5, 428750); // charmeleon nivel 35 → umbral 37810

        // Solo Fuego completo: todavía no puede (falta Volador)
        ReclutadoExpTipo::create(['reclutado_id' => $reclutado->id, 'tipo' => 'Fuego', 'cantidad' => 37810]);
        $this->assertFalse(ServicioEvolucion::puedeEvolucionar($reclutado->fresh()));

        ReclutadoExpTipo::create(['reclutado_id' => $reclutado->id, 'tipo' => 'Volador', 'cantidad' => 37810]);
        $this->assertTrue(ServicioEvolucion::puedeEvolucionar($reclutado->fresh()));
    }

    public function test_dar_caramelo_descunta_pool_y_suma_100_al_reclutado(): void
    {
        $this->crearCadenaCharmander();
        $reclutado = $this->crearReclutado(4, 33750);
        CarameloTipo::create(['tipo' => 'Fuego', 'cantidad' => 3]);

        $response = $this->postJson("/reclutado/{$reclutado->id}/dar-caramelo", ['tipo' => 'Fuego']);

        $response->assertOk()->assertJson([
            'success' => true,
            'actual' => 100,
            'caramelos_disponibles' => 2,
            'puede_evolucionar' => false,
        ]);
        $this->assertDatabaseHas('caramelos_tipo', ['tipo' => 'Fuego', 'cantidad' => 2]);
        $this->assertDatabaseHas('reclutados_exp_tipo', [
            'reclutado_id' => $reclutado->id,
            'tipo' => 'Fuego',
            'cantidad' => 100,
        ]);
    }

    public function test_dar_caramelo_422_sin_caramelos_disponibles(): void
    {
        $this->crearCadenaCharmander();
        $reclutado = $this->crearReclutado(4, 33750);
        CarameloTipo::create(['tipo' => 'Fuego', 'cantidad' => 0]);

        $response = $this->postJson("/reclutado/{$reclutado->id}/dar-caramelo", ['tipo' => 'Fuego']);

        $response->assertUnprocessable()->assertJson(['error' => 'No hay caramelos de tipo Fuego']);
        $this->assertDatabaseHas('caramelos_tipo', ['tipo' => 'Fuego', 'cantidad' => 0]);
        $this->assertDatabaseMissing('reclutados_exp_tipo', ['reclutado_id' => $reclutado->id]);
    }

    public function test_dar_caramelo_422_si_el_tipo_no_es_requerido(): void
    {
        $this->crearCadenaCharmander();
        $reclutado = $this->crearReclutado(4, 33750);
        CarameloTipo::create(['tipo' => 'Agua', 'cantidad' => 5]);

        $response = $this->postJson("/reclutado/{$reclutado->id}/dar-caramelo", ['tipo' => 'Agua']);

        $response->assertUnprocessable()->assertJson(['error' => 'Ese tipo no es necesario para la evolución']);
        $this->assertDatabaseMissing('reclutados_exp_tipo', ['reclutado_id' => $reclutado->id]);
    }

    public function test_evolucionar_cambia_pokemon_consume_exp_y_despacha_pokedex(): void
    {
        Bus::fake();
        $this->crearCadenaCharmander();
        $reclutado = $this->crearReclutado(4, 33750);
        ReclutadoExpTipo::create(['reclutado_id' => $reclutado->id, 'tipo' => 'Fuego', 'cantidad' => 8000]);

        $response = $this->postJson("/reclutado/{$reclutado->id}/evolucionar");

        $response->assertOk()->assertJson(['success' => true, 'pokemon_id' => 5]);
        $this->assertSame(5, $reclutado->fresh()->pokemon_id);
        // 8000 - 7210 = 790 restantes
        $this->assertDatabaseHas('reclutados_exp_tipo', [
            'reclutado_id' => $reclutado->id,
            'tipo' => 'Fuego',
            'cantidad' => 790,
        ]);
        Bus::assertDispatched(ActualizarPokedexJob::class, function ($job) {
            return $job->pokemonId === 5 && $job->estado === 'RECLUTADO';
        });
    }

    public function test_evolucionar_borra_filas_de_exp_que_llegan_a_cero(): void
    {
        Bus::fake();
        $this->crearCadenaCharmander();
        $reclutado = $this->crearReclutado(4, 33750);
        ReclutadoExpTipo::create(['reclutado_id' => $reclutado->id, 'tipo' => 'Fuego', 'cantidad' => 7210]);

        $this->postJson("/reclutado/{$reclutado->id}/evolucionar")->assertOk();

        $this->assertDatabaseMissing('reclutados_exp_tipo', ['reclutado_id' => $reclutado->id]);
    }

    public function test_evolucionar_422_si_no_cumple_requisitos(): void
    {
        $this->crearCadenaCharmander();
        $reclutado = $this->crearReclutado(4, 33750);
        ReclutadoExpTipo::create(['reclutado_id' => $reclutado->id, 'tipo' => 'Fuego', 'cantidad' => 5000]);

        $response = $this->postJson("/reclutado/{$reclutado->id}/evolucionar");

        $response->assertUnprocessable()->assertJson(['error' => 'No cumple los requisitos']);
        $this->assertSame(4, $reclutado->fresh()->pokemon_id);
    }

    public function test_evolucionar_doble_tipo_consume_ambos_y_despacha_el_nuevo_pokemon(): void
    {
        Bus::fake();
        $this->crearCadenaCharmander();
        $reclutado = $this->crearReclutado(5, 428750); // charmeleon nivel 35 → umbral 37810
        ReclutadoExpTipo::create(['reclutado_id' => $reclutado->id, 'tipo' => 'Fuego', 'cantidad' => 37810]);
        ReclutadoExpTipo::create(['reclutado_id' => $reclutado->id, 'tipo' => 'Volador', 'cantidad' => 40000]);

        $response = $this->postJson("/reclutado/{$reclutado->id}/evolucionar");

        $response->assertOk()->assertJson(['success' => true, 'pokemon_id' => 6]);
        $this->assertSame(6, $reclutado->fresh()->pokemon_id);
        // Fuego llega a 0 → fila eliminada; Volador conserva 40000 - 37810 = 2190
        $this->assertDatabaseMissing('reclutados_exp_tipo', [
            'reclutado_id' => $reclutado->id,
            'tipo' => 'Fuego',
        ]);
        $this->assertDatabaseHas('reclutados_exp_tipo', [
            'reclutado_id' => $reclutado->id,
            'tipo' => 'Volador',
            'cantidad' => 2190,
        ]);
        Bus::assertDispatched(ActualizarPokedexJob::class, function ($job) {
            return $job->pokemonId === 6 && $job->estado === 'RECLUTADO';
        });
    }

    public function test_show_devuelve_vista_con_datos_del_reclutado(): void
    {
        $this->crearCadenaCharmander();
        $reclutado = $this->crearReclutado(4, 33750);
        CarameloTipo::create(['tipo' => 'Fuego', 'cantidad' => 5]);

        $response = $this->get("/reclutado/{$reclutado->id}");

        $response->assertOk();
        $data = $response->viewData('reclutado');
        $this->assertSame($reclutado->id, $data['id']);
        $this->assertSame(4, $data['pokemon_id']);
        $this->assertSame(15, $data['nivel']);
        $this->assertSame('/images/iconos/4.png', $data['imagen']);
        $this->assertSame(33750, $data['exp_total']);

        $this->assertSame(5, $response->viewData('siguiente')['pokemon_id']);
        $this->assertFalse($response->viewData('puedeEvolucionar'));

        $requisitos = $response->viewData('requisitos');
        $this->assertCount(1, $requisitos);
        $this->assertSame('Fuego', $requisitos[0]['tipo']);
        $this->assertSame(7210, $requisitos[0]['necesario']);
        $this->assertSame(5, $requisitos[0]['caramelosDisponibles']);
    }
}
