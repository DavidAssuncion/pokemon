<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TipoEnum;
use App\Jobs\ActualizarPokedexJob;
use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\PlayerInventory;
use App\Models\Pokemon;
use App\Models\PokemonEvolution;
use App\Models\PokemonType;
use App\Models\Province;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Src\Reclutamiento\App\ServicioEvolucion;
use Src\Shared\Domain\SlugTipo;
use Tests\TestCase;

class ReclutadoOpcionesEvolucionTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::factory()->create();
        $this->actingAs($this->usuario);
    }

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

    private function crearEevee(bool $conFormaAlterna = false): void
    {
        // Eevee base (species 133)
        $this->crearPokemon(133, 'eevee', 133);
        // Evoluciones de Eevee
        $this->crearPokemon(134, 'vaporeon', 134);
        $this->crearPokemon(135, 'jolteon', 135);
        $this->crearPokemon(136, 'flareon', 136);
        $this->crearPokemon(700, 'sylveon', 700);

        $this->crearEvolucion(133, 134, 20);
        $this->crearEvolucion(133, 135, 20);
        $this->crearEvolucion(133, 136, 20);
        $this->crearEvolucion(133, 700, 20);

        PokemonType::create(['pokemon_id' => 134, 'type' => TipoEnum::WATER, 'slot' => 1]);   // Agua
        PokemonType::create(['pokemon_id' => 135, 'type' => TipoEnum::ELECTRIC, 'slot' => 1]); // Eléctrico
        PokemonType::create(['pokemon_id' => 136, 'type' => TipoEnum::FIRE, 'slot' => 1]);     // Fuego
        PokemonType::create(['pokemon_id' => 700, 'type' => TipoEnum::FAIRY, 'slot' => 1]);    // Hada

        if ($conFormaAlterna) {
            $this->crearPokemon(10342, 'eevee-gmax', 133);
            $this->crearEvolucion(133, 10342, 40);
            PokemonType::create(['pokemon_id' => 10342, 'type' => TipoEnum::NORMAL, 'slot' => 1]);
        }
    }

    private function crearReclutado(int $pokemonId, int $expTotal, string $nombre = 'Eevee'): Reclutado
    {
        return Reclutado::create([
            'user_id' => $this->usuario->id,
            'nombre' => $nombre,
            'pokemon_id' => $pokemonId,
            'exp' => ['total' => $expTotal],
            'es_shiny' => false,
            'obj_equipados' => [],
            'movimientos' => [],
        ]);
    }

    private function crearInventarioTipo(string $tipo, int $cantidad): void
    {
        PlayerInventory::create([
            'user_id' => $this->usuario->id,
            'item_key' => 'tipo:'.SlugTipo::de($tipo),
            'cantidad' => $cantidad,
        ]);
    }

    public function test_opciones_evolucion_devuelve_varias_opciones_para_eevee(): void
    {
        $this->crearEevee();

        $opciones = ServicioEvolucion::opcionesEvolucion(Pokemon::findOrFail(133));

        $this->assertCount(4, $opciones);
        $this->assertSame([134, 135, 136, 700], array_map(fn (Pokemon $p): int => $p->id, $opciones));
        // Todos con types cargados (sin N+1)
        foreach ($opciones as $opcion) {
            $this->assertTrue($opcion->relationLoaded('types'));
        }
    }

    public function test_siguiente_evolucion_es_la_primera_opcion_para_eevee(): void
    {
        $this->crearEevee();

        $siguiente = ServicioEvolucion::siguienteEvolucion(Pokemon::findOrFail(133));

        $this->assertNotNull($siguiente);
        $this->assertSame(134, $siguiente->id);
    }

    public function test_requisitos_de_opciones_devuelve_todas_con_requisitos_y_puede_evolucionar(): void
    {
        $this->crearEevee();
        $reclutado = $this->crearReclutado(133, 0);
        $this->crearInventarioTipo('Agua', 3);
        $this->crearInventarioTipo('Fuego', 1);

        $opciones = ServicioEvolucion::requisitosDeOpciones($reclutado, $this->usuario->id);

        $this->assertCount(4, $opciones);
        $this->assertSame([134, 135, 136, 700], array_column($opciones, 'pokemon_id'));
        $this->assertSame('vaporeon', $opciones[0]['nombre']);
        $this->assertSame('/images/iconos_webp/134.webp', $opciones[0]['imagen']);
        $this->assertSame(['Agua'], array_column($opciones[0]['requisitos'], 'tipo'));
        $this->assertSame(3, $opciones[0]['requisitos'][0]['caramelosDisponibles']);
        $this->assertFalse($opciones[0]['puede_evolucionar']);
    }

    public function test_evolucionar_con_varias_opciones_sin_destino_devuelve_422(): void
    {
        $this->crearEevee();
        $reclutado = $this->crearReclutado(133, 0);
        $reclutado->update(['exp' => ['total' => 0, 'tipos' => ['Agua' => 1000, 'Eléctrico' => 1000, 'Fuego' => 1000, 'Hada' => 1000]]]);

        $response = $this->postJson("/reclutado/{$reclutado->id}/evolucionar");

        $response->assertUnprocessable()->assertJson(['error' => 'Selecciona a qué pokémon evolucionar']);
        $this->assertSame(133, $reclutado->fresh()->pokemon_id);
    }

    public function test_evolucionar_con_destino_valido_evoluciona_a_ese(): void
    {
        Bus::fake();
        $this->crearEevee();
        $reclutado = $this->crearReclutado(133, 0);
        $reclutado->update(['exp' => ['total' => 0, 'tipos' => ['Eléctrico' => 1000]]]);

        $response = $this->postJson("/reclutado/{$reclutado->id}/evolucionar", ['evolved_species_id' => 135]);

        $response->assertOk()->assertJson(['success' => true, 'pokemon_id' => 135]);
        $this->assertSame(135, $reclutado->fresh()->pokemon_id);
        Bus::assertDispatched(ActualizarPokedexJob::class, function ($job) {
            return $job->pokemonId === 135 && $job->estado === 'RECLUTADO';
        });
    }

    public function test_evolucionar_con_destino_invalido_devuelve_422(): void
    {
        $this->crearEevee();
        $reclutado = $this->crearReclutado(133, 0);
        $reclutado->update(['exp' => ['total' => 0, 'tipos' => ['Agua' => 1000]]]);

        $response = $this->postJson("/reclutado/{$reclutado->id}/evolucionar", ['evolved_species_id' => 6]);

        $response->assertUnprocessable()->assertJson(['error' => 'Evolución no válida']);
        $this->assertSame(133, $reclutado->fresh()->pokemon_id);
    }

    public function test_dar_caramelo_con_destino_concreto_rechaza_tipo_no_requerido(): void
    {
        $this->crearEevee();
        $reclutado = $this->crearReclutado(133, 0);
        $this->crearInventarioTipo('Fuego', 5);

        // Destino jolteon (135) requiere Eléctrico → dar Fuego debe fallar
        $response = $this->postJson(
            "/reclutado/{$reclutado->id}/dar-caramelo",
            ['tipo' => 'Fuego', 'evolved_species_id' => 135]
        );

        $response->assertUnprocessable()->assertJson(['error' => 'Ese tipo no es necesario para la evolución']);
        $this->assertSame(0, $reclutado->fresh()->exp->expTipo('Fuego'));
    }

    public function test_dar_caramelo_con_destino_concreto_acepta_tipo_requerido(): void
    {
        $this->crearEevee();
        $reclutado = $this->crearReclutado(133, 0);
        $this->crearInventarioTipo('Eléctrico', 3);

        $response = $this->postJson(
            "/reclutado/{$reclutado->id}/dar-caramelo",
            ['tipo' => 'Eléctrico', 'evolved_species_id' => 135]
        );

        $response->assertOk()->assertJson([
            'success' => true,
            'actual' => 100,
            'caramelos_disponibles' => 2,
        ]);
        $this->assertSame(100, $reclutado->fresh()->exp->expTipo('Eléctrico'));
    }

    public function test_dar_caramelo_en_pokemon_de_equipo_en_exploracion_devuelve_422(): void
    {
        $this->crearEevee();
        $reclutado = $this->crearReclutado(133, 0);
        $this->crearInventarioTipo('Agua', 5);

        $team = Team::create(['name' => 'Explorando', 'user_id' => $this->usuario->id]);
        TeamMember::create([
            'team_id' => $team->id,
            'pokemon_id' => $reclutado->id,
            'slot' => 1,
            'behavior' => 'VANGUARDIA',
        ]);
        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => $province->id]);
        ExploracionActiva::create([
            'user_id' => $this->usuario->id,
            'equipo_id' => $team->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
            'duracion_horas' => 2,
            'inicio_exploracion' => now(),
            'llegada_destino' => now()->addHour(),
            'regreso' => null,
        ]);

        $response = $this->postJson("/reclutado/{$reclutado->id}/dar-caramelo", ['tipo' => 'Agua']);

        $response->assertUnprocessable();
        $this->assertSame(0, $reclutado->fresh()->exp->expTipo('Agua'));
    }

    public function test_evoluciones_endpoint_devuelve_opciones_con_requisitos_y_puede_evolucionar(): void
    {
        $this->crearEevee();
        $reclutado = $this->crearReclutado(133, 0);
        $this->crearInventarioTipo('Agua', 2);

        $response = $this->getJson("/reclutado/{$reclutado->id}/evoluciones");

        $response->assertOk()->assertJsonStructure([
            'opciones' => [
                '*' => [
                    'pokemon_id',
                    'nombre',
                    'imagen',
                    'requisitos' => [
                        '*' => ['tipo', 'slug', 'necesario', 'actual', 'caramelosDisponibles'],
                    ],
                    'puede_evolucionar',
                ],
            ],
        ]);
        $opciones = $response->json('opciones');
        $this->assertCount(4, $opciones);
        $this->assertSame([134, 135, 136, 700], array_column($opciones, 'pokemon_id'));
    }

    // ==========================================
    // Bug 1: evoluciones regionales (ids ≥ 10000)
    // ==========================================

    public function test_opciones_evolucion_sandshrew_alola_devuelve_sandslash_alola(): void
    {
        $this->crearPokemon(10101, 'sandshrew-alola', 10101);
        $this->crearPokemon(10102, 'sandslash-alola', 10102);
        $this->crearEvolucion(10101, 10102, 22);

        $opciones = ServicioEvolucion::opcionesEvolucion(Pokemon::findOrFail(10101));

        $this->assertCount(1, $opciones);
        $this->assertSame(10102, $opciones[0]->id);
    }

    public function test_opciones_evolucion_cubone_devuelve_marowak_normal_y_alola(): void
    {
        $this->crearPokemon(104, 'cubone', 104);
        $this->crearPokemon(105, 'marowak', 105);
        $this->crearPokemon(10115, 'marowak-alola', 10115);
        $this->crearEvolucion(104, 105, 28);
        $this->crearEvolucion(104, 10115, 28);

        $opciones = ServicioEvolucion::opcionesEvolucion(Pokemon::findOrFail(104));

        $this->assertCount(2, $opciones);
        $this->assertSame([105, 10115], array_map(fn (Pokemon $p): int => $p->id, $opciones));
    }

    public function test_siguiente_evolucion_cubone_sigue_siendo_marowak_normal(): void
    {
        $this->crearPokemon(104, 'cubone', 104);
        $this->crearPokemon(105, 'marowak', 105);
        $this->crearPokemon(10115, 'marowak-alola', 10115);
        $this->crearEvolucion(104, 105, 28);
        $this->crearEvolucion(104, 10115, 28);

        $siguiente = ServicioEvolucion::siguienteEvolucion(Pokemon::findOrFail(104));

        $this->assertNotNull($siguiente);
        $this->assertSame(105, $siguiente->id);
        $this->assertSame('marowak', $siguiente->name);
    }

    public function test_opciones_evolucion_exeggcute_devuelve_exeggutor_normal_y_alola(): void
    {
        $this->crearPokemon(102, 'exeggcute', 102);
        $this->crearPokemon(103, 'exeggutor', 103);
        $this->crearPokemon(10114, 'exeggutor-alola', 10114);
        $this->crearEvolucion(102, 103, 36);
        $this->crearEvolucion(102, 10114, 36);

        $opciones = ServicioEvolucion::opcionesEvolucion(Pokemon::findOrFail(102));

        $this->assertCount(2, $opciones);
        $this->assertSame([103, 10114], array_map(fn (Pokemon $p): int => $p->id, $opciones));
    }

    public function test_opciones_evolucion_pikachu_devuelve_raichu_normal_y_alola(): void
    {
        $this->crearPokemon(25, 'pikachu', 25);
        $this->crearPokemon(26, 'raichu', 26);
        $this->crearPokemon(10100, 'raichu-alola', 10100);
        $this->crearEvolucion(25, 26, 22);
        $this->crearEvolucion(25, 10100, 22);

        $opciones = ServicioEvolucion::opcionesEvolucion(Pokemon::findOrFail(25));

        $this->assertCount(2, $opciones);
        $this->assertSame([26, 10100], array_map(fn (Pokemon $p): int => $p->id, $opciones));
    }
}
