<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\Province;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExploracionesPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function crearExploracion(array $atributos = []): ExploracionActiva
    {
        $province = Province::create(['name' => 'Kanto']);
        $habitat = Habitat::create(['name' => 'Bosque', 'province_id' => $province->id]);
        $team = Team::create(['name' => 'Equipo Test']);

        return ExploracionActiva::create(array_merge([
            'equipo_id' => $team->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
            'duracion_horas' => 4,
            'hora_limite' => null,
            'indefinido' => false,
            'eventos' => ['bitacora' => [], 'ultimo_procesado' => now()->toIso8601String()],
            'inicio_exploracion' => now()->subHour(),
            'llegada_destino' => null,
            'regreso' => null,
        ], $atributos));
    }

    /**
     * @return array{chainId: int, bulbasaur: Pokemon, charmander: Pokemon}
     */
    private function crearPokemons(): array
    {
        $chainId = 51;

        $bulbasaur = Pokemon::create([
            'name' => 'bulbasaur',
            'species_id' => 1,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
            'hatch' => 10,
            'evolution_chain_id' => $chainId,
        ]);

        $charmander = Pokemon::create([
            'name' => 'charmander',
            'species_id' => 4,
            'capture_rate' => 45,
            'base_experience' => 62,
            'height' => 6,
            'weight' => 85,
            'hatch' => 10,
            'evolution_chain_id' => $chainId,
        ]);

        return [
            'chainId' => $chainId,
            'bulbasaur' => $bulbasaur,
            'charmander' => $charmander,
        ];
    }

    public function test_index_returns_200_y_la_vista_exploraciones(): void
    {
        $this->get('/exploraciones')
            ->assertOk()
            ->assertViewIs('exploraciones.index');
    }

    public function test_index_sin_datos_devuelve_arrays_vacios(): void
    {
        $response = $this->get('/exploraciones');

        $response->assertOk();
        $this->assertSame([], $response->viewData('activas'));
        $this->assertSame([], $response->viewData('terminadas'));
    }

    public function test_activas_contiene_bitacora_transformada(): void
    {
        $pokemons = $this->crearPokemons();
        $exploracion = $this->crearExploracion([
            'eventos' => [
                'bitacora' => [
                    ['tipo' => 'pokemon', 'pokemon_id' => $pokemons['bulbasaur']->id, 'timestamp' => '2026-08-27T12:37:12Z'],
                    ['tipo' => 'caramelo_familia', 'pokemon_id' => $pokemons['charmander']->id, 'cantidad' => 2, 'timestamp' => '2026-08-27T12:40:00Z'],
                    ['tipo' => 'caramelo_ev', 'stat' => 2, 'cantidad' => 1, 'timestamp' => '2026-08-27T12:42:31Z'],
                ],
                'ultimo_procesado' => now()->toIso8601String(),
            ],
        ]);

        $response = $this->get('/exploraciones');

        $response->assertOk();
        /** @var list<array<string, mixed>> $activas */
        $activas = $response->viewData('activas');
        $this->assertCount(1, $activas);

        $activa = $activas[0];
        $this->assertSame($exploracion->id, $activa['id']);
        $this->assertSame('Equipo Test', $activa['equipo']);
        $this->assertSame('Bosque', $activa['habitat']);
        $this->assertSame($exploracion->habitat_id, $activa['habitat_id']);
        $this->assertSame(1, $activa['nivel']);
        $this->assertFalse($activa['indefinido']);
        $this->assertSame(4, $activa['duracion_horas']);

        $inicio = Carbon::parse($activa['inicio']);
        $this->assertTrue($inicio->equalTo($exploracion->inicio_exploracion));
        $this->assertTrue(Carbon::parse($activa['fin'])->equalTo($inicio->copy()->addHours(4)));
        $this->assertTrue(Carbon::parse($activa['inicio_vuelta'])->equalTo($inicio->copy()->addHours(3)));

        $this->assertSame('explorando', $activa['estado']);
        $this->assertSame(25, $activa['progreso']);

        $bitacora = $activa['bitacora'];
        $this->assertCount(3, $bitacora);
        $this->assertSame('bulbasaur', $bitacora[0]['nombre']);
        $this->assertSame($pokemons['bulbasaur']->id, $bitacora[0]['pokemon_id']);
        $this->assertSame('charmander', $bitacora[1]['nombre']);
        $this->assertSame(2, $bitacora[1]['cantidad']);
        $this->assertSame('Ataque', $bitacora[2]['stat_nombre']);
        $this->assertSame('atk', $bitacora[2]['stat_slug']);
        $this->assertSame(1, $bitacora[2]['cantidad']);
        $this->assertSame('2026-08-27T12:37:12Z', $bitacora[0]['timestamp']);
    }

    public function test_activa_en_vuelta_marca_volviendo_y_progreso(): void
    {
        $this->travelTo(Carbon::parse('2026-08-28 12:00:00'));

        $this->crearPokemons();
        // duración 4h, inicio hace 3h → fin 13:00, vuelta 12:00 = ahora → volviendo, 75%
        $this->crearExploracion(['inicio_exploracion' => now()->subHours(3)]);

        /** @var list<array<string, mixed>> $activas */
        $activas = $this->get('/exploraciones')->viewData('activas');
        $this->assertSame('volviendo', $activas[0]['estado']);
        $this->assertSame(75, $activas[0]['progreso']);

        $this->travelBack();
    }

    public function test_indefinida_sin_fin_ni_vuelta_estado_explorando_y_progreso_cero(): void
    {
        $this->crearPokemons();
        $this->crearExploracion(['indefinido' => true, 'duracion_horas' => null]);

        /** @var list<array<string, mixed>> $activas */
        $activas = $this->get('/exploraciones')->viewData('activas');
        $this->assertTrue($activas[0]['indefinido']);
        $this->assertNull($activas[0]['duracion_horas']);
        $this->assertNull($activas[0]['fin']);
        $this->assertNull($activas[0]['inicio_vuelta']);
        $this->assertSame('explorando', $activas[0]['estado']);
        $this->assertSame(0, $activas[0]['progreso']);
    }

    public function test_terminadas_incluyen_resumen_de_resultado(): void
    {
        $pokemons = $this->crearPokemons();
        $this->crearExploracion([
            'regreso' => now()->subMinutes(5),
            'eventos' => [
                'derrotados' => [$pokemons['bulbasaur']->id, $pokemons['bulbasaur']->id],
                'resultado' => [
                    'avistados' => [
                        ['pokemon_id' => $pokemons['bulbasaur']->id, 'nombre' => 'bulbasaur'],
                    ],
                    'capturados' => [
                        ['pokemon_id' => $pokemons['bulbasaur']->id, 'nombre' => 'bulbasaur', 'cantidad' => 2],
                    ],
                    'caramelos_familia' => [
                        ['evolution_chain_id' => $pokemons['chainId'], 'nombre' => 'bulbasaur', 'pokemon_id' => $pokemons['bulbasaur']->id, 'cantidad' => 3],
                    ],
                    'caramelos_ev' => [
                        ['stat' => 2, 'cantidad' => 4],
                    ],
                    'exp' => 250,
                ],
            ],
        ]);

        /** @var list<array<string, mixed>> $terminadas */
        $terminadas = $this->get('/exploraciones')->viewData('terminadas');
        $this->assertCount(1, $terminadas);
        $this->assertSame('Equipo Test', $terminadas[0]['equipo']);
        $this->assertSame('Bosque', $terminadas[0]['habitat']);
        $this->assertSame(1, $terminadas[0]['nivel']);

        $resultado = $terminadas[0]['resultado'];
        $this->assertSame(
            [['pokemon_id' => $pokemons['bulbasaur']->id, 'nombre' => 'bulbasaur']],
            $resultado['avistados'],
        );
        $this->assertSame(
            [['pokemon_id' => $pokemons['bulbasaur']->id, 'nombre' => 'bulbasaur', 'cantidad' => 2]],
            $resultado['capturados'],
        );
        $this->assertSame(
            [['evolution_chain_id' => $pokemons['chainId'], 'nombre' => 'bulbasaur', 'pokemon_id' => $pokemons['bulbasaur']->id, 'cantidad' => 3]],
            $resultado['caramelos_familia'],
        );
        $this->assertSame(
            [['stat' => 2, 'stat_nombre' => 'Ataque', 'stat_slug' => 'atk', 'cantidad' => 4]],
            $resultado['caramelos_ev'],
        );
        $this->assertSame(250, $resultado['exp']);
    }

    public function test_terminada_sin_resultado_devuelve_resumen_vacio(): void
    {
        $this->crearPokemons();
        $this->crearExploracion(['regreso' => now()->subMinutes(5)]);

        /** @var list<array<string, mixed>> $terminadas */
        $terminadas = $this->get('/exploraciones')->viewData('terminadas');
        $this->assertCount(1, $terminadas);
        $this->assertSame([
            'avistados' => [],
            'capturados' => [],
            'caramelos_familia' => [],
            'caramelos_ev' => [],
            'caramelos_tipo' => [],
            'exp' => 0,
        ], $terminadas[0]['resultado']);
    }

    public function test_activas_no_incluyen_las_completadas(): void
    {
        $this->crearPokemons();
        $this->crearExploracion(['regreso' => now()->subMinutes(5)]);
        $this->crearExploracion();

        $response = $this->get('/exploraciones');

        $this->assertCount(1, $response->viewData('activas'));
        $this->assertCount(1, $response->viewData('terminadas'));
    }

    public function test_cerrar_elimina_la_exploracion_completada(): void
    {
        $exploracion = $this->crearExploracion(['regreso' => now()->subMinutes(5)]);

        $this->post("/exploraciones/{$exploracion->id}/cerrar")
            ->assertRedirect();

        $this->assertDatabaseMissing('exploraciones_activas', ['id' => $exploracion->id]);
    }

    public function test_cerrar_responde_json_cuando_se_pide(): void
    {
        $exploracion = $this->crearExploracion(['regreso' => now()->subMinutes(5)]);

        $this->postJson("/exploraciones/{$exploracion->id}/cerrar")
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('exploraciones_activas', ['id' => $exploracion->id]);
    }

    public function test_cerrar_exploracion_activa_devuelve_404(): void
    {
        $exploracion = $this->crearExploracion();

        $this->post("/exploraciones/{$exploracion->id}/cerrar")
            ->assertNotFound();

        $this->assertDatabaseHas('exploraciones_activas', ['id' => $exploracion->id]);
    }
}
