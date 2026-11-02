<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\Province;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExploracionesPageTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        // Multi-jugador: las rutas del juego requieren autenticación y las tablas
        // player-owned (teams, exploraciones_activas) tienen user_id NOT NULL.
        // Los tests HTTP se ejecutan como un usuario autenticado dueño de los datos.
        $this->usuario = User::factory()->create();
        $this->actingAs($this->usuario);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function crearExploracion(array $atributos = []): ExploracionActiva
    {
        $province = Province::create(['name' => 'Kanto']);
        $habitat = Habitat::create(['name' => 'Bosque', 'province_id' => $province->id]);
        $reclutado = $this->crearReclutado();

        return ExploracionActiva::create(array_merge([
            'user_id' => $this->usuario->id,
            'reclutado_id' => $reclutado->id,
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
     * Crea un reclutado individual ("equipo" de la expedición tras RF-B/RFC).
     * El nombre 'Equipo Test' preserva las aserciones históricas que leen el
     * campo equipo de las tarjetas de expedición.
     */
    private function crearReclutado(): \App\Models\Reclutado
    {
        $pokemon = Pokemon::firstOrCreate(['id' => 9001], [
            'name' => 'recluta-9001',
            'species_id' => 1,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
            'evolution_chain_id' => 51,
        ]);

        return \App\Models\Reclutado::firstOrCreate(
            ['pokemon_id' => $pokemon->id, 'user_id' => $this->usuario->id],
            ['nombre' => 'Equipo Test', 'exp' => ['total' => 0], 'es_shiny' => false, 'obj_equipados' => [], 'movimientos' => []],
        );
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

        // Personalidad: explorador, diario y fase/tiempo restante (contrato aditivo).
        $this->assertSame('ida', $activa['fase']);
        $this->assertIsInt($activa['restante_min']);
        $this->assertGreaterThan(170, $activa['restante_min']);
        $this->assertMatchesRegularExpression('/^\d+ h( \d+ min)?$/', $activa['restante_label']);
        $this->assertSame('Equipo Test', $activa['explorador']['nombre']);
        $this->assertSame(3, $activa['diario']['total']);
        $this->assertSame($activa['diario']['eventos'], $activa['bitacora']);

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

    public function test_index_renderiza_diario_con_candy_imagen_detalle_derrota_y_reason_texto(): void
    {
        $pokemons = $this->crearPokemons();

        $this->crearExploracion([
            'eventos' => [
                'bitacora' => [
                    ['tipo' => 'caramelo_familia', 'pokemon_id' => $pokemons['charmander']->id, 'nombre' => 'charmander', 'cantidad' => 2, 'timestamp' => '2026-08-27T12:40:00Z'],
                    [
                        'tipo' => 'encuentro',
                        'subtype' => 'normal',
                        'pokemon_id' => $pokemons['bulbasaur']->id,
                        'nombre' => 'bulbasaur',
                        'resolucion' => 'derrota',
                        'hp_inicial' => 39,
                        'hp_max' => 39,
                        'hp_final' => 0,
                        'rival_hp_inicial' => 34,
                        'rival_hp_final' => 12,
                        'rival_hp_max' => 34,
                        'log' => ['--- Ronda 1 ---', '--- Ronda 2 ---', '--- Ronda 3 ---', '--- Ronda 4 ---'],
                        'timestamp' => '2026-08-27T12:45:00Z',
                    ],
                    ['tipo' => 'derrota', 'reason' => 'explorador_debilitado', 'timestamp' => '2026-08-27T12:50:00Z'],
                ],
                'ultimo_procesado' => now()->toIso8601String(),
            ],
        ]);

        $response = $this->get('/exploraciones');

        $response->assertOk();
        // candy_imagen se resuelve a partir del pokemon_id de la familia.
        $response->assertSee('/images/iconos_webp/'.$pokemons['charmander']->id.'.webp', false);
        $response->assertSee('Caramelos de', false);
        // detalle_derrota se imprime tal cual.
        $response->assertSee('Entró con 39/39 PS tras 4 rondas; a bulbasaur le quedaban 12/34 PS.', false);
        // reason_texto humano; el motivo técnico no se muestra en crudo.
        $response->assertSee('se quedó sin PS', false);
        $response->assertDontSee('explorador_debilitado', false);
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
                    'exp_reclutado' => 125,
                ],
            ],
        ]);

        $response = $this->get('/exploraciones');

        /** @var list<array<string, mixed>> $terminadas */
        $terminadas = $response->viewData('terminadas');
        $this->assertCount(1, $terminadas);
        $this->assertSame('Equipo Test', $terminadas[0]['equipo']);
        $this->assertSame('Bosque', $terminadas[0]['habitat']);
        $this->assertSame(1, $terminadas[0]['nivel']);

        $resultado = $terminadas[0]['resultado'];
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
        // derrotados expuesto en el array raíz de la terminada
        $this->assertSame(
            [$pokemons['bulbasaur']->id, $pokemons['bulbasaur']->id],
            $terminadas[0]['derrotados'],
        );

        // Personalidad: explorador, diario, hitos y EXP del reclutado.
        $this->assertSame('Equipo Test', $terminadas[0]['explorador']['nombre']);
        $this->assertArrayHasKey('diario', $terminadas[0]);
        $this->assertSame(0, $terminadas[0]['diario']['total']);
        $this->assertSame(125, $terminadas[0]['exp_reclutado']);
        $this->assertSame(125, $terminadas[0]['vista']['exp_reclutado']);
        $this->assertIsArray($terminadas[0]['hitos']);
        $this->assertSame($terminadas[0]['hitos'], $terminadas[0]['vista']['hitos']);

        // Capturados y caramelos se agrupan bajo la columna de obtenidas.
        $response->assertSee('Recompensas obtenidas', false);
        $response->assertSee('Capturados', false);
    }

    public function test_terminada_expone_objetos_perdidos_en_resultado_y_vista(): void
    {
        $base = Pokemon::create([
            'id' => 7001,
            'name' => 'bulbasaur',
            'species_id' => 0,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
            'evolution_chain_id' => 51,
        ]);

        $this->crearExploracion([
            'regreso' => now()->subMinutes(5),
            'eventos' => [
                'objetos_perdidos' => [
                    ['tipo' => 'familia', 'id' => 51, 'label' => 'bulbasaur', 'cantidad_perdida' => 4],
                    ['tipo' => 'ev', 'id' => 2, 'label' => 'Ataque', 'cantidad_perdida' => 3],
                ],
                'derrota' => [
                    'reason' => 'explorador_debilitado',
                    'timestamp' => now()->toIso8601String(),
                ],
                'resultado' => [
                    'capturados' => [],
                    'caramelos_familia' => [],
                    'caramelos_ev' => [],
                    'caramelos_tipo' => [],
                    'exp' => 10,
                ],
            ],
        ]);

        $response = $this->get('/exploraciones');
        $response->assertOk();

        /** @var list<array<string, mixed>> $terminadas */
        $terminadas = $response->viewData('terminadas');
        $this->assertCount(1, $terminadas);

        $perdidas = [
            ['tipo' => 'familia', 'id' => 51, 'label' => 'bulbasaur', 'cantidad_perdida' => 4],
            ['tipo' => 'ev', 'id' => 2, 'label' => 'Ataque', 'cantidad_perdida' => 3],
        ];

        // El contrato persistido no cambia: resultado conserva solo las claves
        // guardadas en eventos['objetos_perdidos'].
        $this->assertEquals($perdidas, $terminadas[0]['resultado']['objetos_perdidos']);

        // La vista añade tipo_label e imagen resueltos en presentación.
        // eventos es jsonb en pgsql y no conserva el orden de claves.
        $this->assertSame([
            [
                'tipo' => 'familia',
                'tipo_label' => 'Familia',
                'id' => 51,
                'label' => 'bulbasaur',
                'imagen' => "/images/candy_pokemon/{$base->id}.webp",
                'cantidad_perdida' => 4,
            ],
            [
                'tipo' => 'ev',
                'tipo_label' => 'EV',
                'id' => 2,
                'label' => 'Ataque',
                'imagen' => '/images/candy_ev/atk.webp',
                'cantidad_perdida' => 3,
            ],
        ], $terminadas[0]['vista']['objetos_perdidos']);

        $this->assertSame(
            'Tu explorador cayó debilitado en combate y perdió parte del botín.',
            $terminadas[0]['vista']['motivo_perdida'],
        );

        $this->assertTrue($terminadas[0]['vista']['tiene_perdidas']);
        $this->assertFalse($terminadas[0]['vista']['tiene_obtenidas']);
        $this->assertSame('lg:col-span-3', $terminadas[0]['vista']['span_perdidas']);
        $this->assertSame('', $terminadas[0]['vista']['span_obtenidas']);

        // La vista renderiza la columna de pérdidas una sola vez, con nombre,
        // imagen, cantidad y el motivo de la pérdida.
        $this->assertSame(1, substr_count($response->getContent(), 'Recompensas perdidas'));
        $this->assertSame(
            1,
            substr_count($response->getContent(), 'Tu explorador cayó debilitado en combate y perdió parte del botín.'),
        );
        $response->assertSee('grayscale', false);
        $response->assertSee('Familia', false);
        $response->assertSee('bulbasaur', false);
        $response->assertSee("/images/candy_pokemon/{$base->id}.webp", false);
        $response->assertSee('×4', false);
        $response->assertSee('Ataque', false);
        $response->assertSee('/images/candy_ev/atk.webp', false);
        $response->assertSee('×3', false);
        // Sin capturados ni caramelos no hay columna de recompensas obtenidas.
        $response->assertDontSee('Recompensas obtenidas', false);
    }

    public function test_terminada_resuelve_nombre_e_imagen_de_perdidas_sin_label(): void
    {
        $base = Pokemon::create([
            'id' => 7001,
            'name' => 'bulbasaur',
            'species_id' => 0,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
            'evolution_chain_id' => 51,
        ]);

        Pokemon::create([
            'id' => 7002,
            'name' => 'ivysaur',
            'species_id' => 2,
            'capture_rate' => 45,
            'base_experience' => 142,
            'height' => 10,
            'weight' => 130,
            'evolution_chain_id' => 51,
        ]);

        Pokemon::create([
            'id' => 1,
            'name' => 'pikachu',
            'species_id' => 25,
            'capture_rate' => 190,
            'base_experience' => 112,
            'height' => 4,
            'weight' => 60,
            'evolution_chain_id' => 10,
        ]);

        $this->crearExploracion([
            'regreso' => now()->subMinutes(5),
            'eventos' => [
                'objetos_perdidos' => [
                    ['tipo' => 'familia', 'id' => 51, 'label' => null, 'cantidad_perdida' => 2],
                    ['tipo' => 'ev', 'id' => 3, 'label' => null, 'cantidad_perdida' => 1],
                    ['tipo' => 'tipo', 'id' => 'roca', 'label' => 'Roca', 'cantidad_perdida' => 2],
                    ['tipo' => 'captura', 'id' => 1, 'label' => null, 'cantidad_perdida' => 1],
                ],
                'derrota' => [
                    'reason' => 'explorador_debilitado',
                    'timestamp' => now()->toIso8601String(),
                ],
                'resultado' => [
                    'capturados' => [],
                    'caramelos_familia' => [],
                    'caramelos_ev' => [],
                    'caramelos_tipo' => [],
                    'exp' => 10,
                ],
            ],
        ]);

        $response = $this->get('/exploraciones');
        $response->assertOk();

        /** @var list<array<string, mixed>> $terminadas */
        $terminadas = $response->viewData('terminadas');
        $this->assertCount(1, $terminadas);

        $this->assertSame([
            [
                'tipo' => 'familia',
                'tipo_label' => 'Familia',
                'id' => 51,
                'label' => 'bulbasaur',
                'imagen' => "/images/candy_pokemon/{$base->id}.webp",
                'cantidad_perdida' => 2,
            ],
            [
                'tipo' => 'ev',
                'tipo_label' => 'EV',
                'id' => 3,
                'label' => 'Defensa',
                'imagen' => '/images/candy_ev/def.webp',
                'cantidad_perdida' => 1,
            ],
            [
                'tipo' => 'tipo',
                'tipo_label' => 'Tipo',
                'id' => 'roca',
                'label' => 'Roca',
                'imagen' => '/images/candy_type/roca.webp',
                'cantidad_perdida' => 2,
            ],
            [
                'tipo' => 'captura',
                'tipo_label' => 'Captura',
                'id' => 1,
                'label' => 'pikachu',
                'imagen' => '/images/iconos_webp/1.webp',
                'cantidad_perdida' => 1,
            ],
        ], $terminadas[0]['vista']['objetos_perdidos']);

        $this->assertSame(
            'Tu explorador cayó debilitado en combate y perdió parte del botín.',
            $terminadas[0]['vista']['motivo_perdida'],
        );

        $this->assertTrue($terminadas[0]['vista']['tiene_perdidas']);
        $this->assertFalse($terminadas[0]['vista']['tiene_obtenidas']);
        $this->assertSame('lg:col-span-3', $terminadas[0]['vista']['span_perdidas']);
        $this->assertSame('', $terminadas[0]['vista']['span_obtenidas']);

        // Los datos históricos sin label se resuelven al renderizar: se muestra
        // el nombre y la imagen, nunca el ID crudo como texto principal.
        $response->assertSee('Recompensas perdidas', false);
        $response->assertSee('grayscale', false);
        $response->assertSee('Familia', false);
        $response->assertSee('bulbasaur', false);
        $response->assertSee("/images/candy_pokemon/{$base->id}.webp", false);
        $response->assertSee('Defensa', false);
        $response->assertSee('/images/candy_ev/def.webp', false);
        $response->assertSee('×2', false);
        $response->assertDontSee('familia 51', false);
    }

    public function test_terminada_sin_resultado_devuelve_resumen_vacio(): void
    {
        $this->crearPokemons();
        $this->crearExploracion(['regreso' => now()->subMinutes(5)]);

        /** @var list<array<string, mixed>> $terminadas */
        $terminadas = $this->get('/exploraciones')->viewData('terminadas');
        $this->assertCount(1, $terminadas);
        $this->assertSame([
            'capturados' => [],
            'caramelos_familia' => [],
            'caramelos_ev' => [],
            'caramelos_tipo' => [],
            'exp' => 0,
            'resultado' => 'exito',
            'duration_real' => 0,
            'tiempo_perdido' => 0,
            'objetos_perdidos' => [],
            'incidentes' => [
                'encuentros' => 0,
                'victorias' => 0,
                'huidas' => 0,
                'emboscadas' => 0,
                'contratiempos' => 0,
            ],
        ], $terminadas[0]['resultado']);
        $this->assertSame([], $terminadas[0]['derrotados']);
        $this->assertSame(0, $terminadas[0]['exp_reclutado']);
        $this->assertSame([], $terminadas[0]['hitos']);
        $this->assertNull($terminadas[0]['vista']['motivo_perdida']);
        $this->assertFalse($terminadas[0]['vista']['tiene_perdidas']);
        $this->assertFalse($terminadas[0]['vista']['tiene_obtenidas']);
        $this->assertSame('', $terminadas[0]['vista']['span_perdidas']);
        $this->assertSame('', $terminadas[0]['vista']['span_obtenidas']);
    }

    public function test_terminada_con_perdidas_sin_derrota_usa_motivo_generico(): void
    {
        $this->crearExploracion([
            'regreso' => now()->subMinutes(5),
            'eventos' => [
                'objetos_perdidos' => [
                    ['tipo' => 'familia', 'id' => 51, 'label' => 'bulbasaur', 'cantidad_perdida' => 1],
                ],
                'resultado' => [
                    'capturados' => [],
                    'caramelos_familia' => [],
                    'caramelos_ev' => [],
                    'caramelos_tipo' => [],
                    'exp' => 10,
                ],
            ],
        ]);

        $response = $this->get('/exploraciones');
        $response->assertOk();

        /** @var list<array<string, mixed>> $terminadas */
        $terminadas = $response->viewData('terminadas');
        $this->assertCount(1, $terminadas);
        $this->assertSame(
            'La expedición terminó en derrota y se perdió parte del botín.',
            $terminadas[0]['vista']['motivo_perdida'],
        );
        $response->assertSee('La expedición terminó en derrota y se perdió parte del botín.', false);
    }

    public function test_terminada_con_perdidas_y_reason_vacio_usa_motivo_generico(): void
    {
        $this->crearExploracion([
            'regreso' => now()->subMinutes(5),
            'eventos' => [
                'objetos_perdidos' => [
                    ['tipo' => 'familia', 'id' => 51, 'label' => 'bulbasaur', 'cantidad_perdida' => 1],
                ],
                'derrota' => [
                    'reason' => '   ',
                    'timestamp' => now()->toIso8601String(),
                ],
                'resultado' => [
                    'capturados' => [],
                    'caramelos_familia' => [],
                    'caramelos_ev' => [],
                    'caramelos_tipo' => [],
                    'exp' => 10,
                ],
            ],
        ]);

        $response = $this->get('/exploraciones');
        $response->assertOk();

        /** @var list<array<string, mixed>> $terminadas */
        $terminadas = $response->viewData('terminadas');
        $this->assertCount(1, $terminadas);
        $this->assertSame(
            'La expedición terminó en derrota y se perdió parte del botín.',
            $terminadas[0]['vista']['motivo_perdida'],
        );
        $response->assertSee('La expedición terminó en derrota y se perdió parte del botín.', false);
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

    public function test_store_con_return_time_guarda_hora_limite_12_50_en_europe_madrid(): void
    {
        // Regresión bug de zona horaria: el return_time '12:50' del usuario se interpretaba
        // como hora UTC (2h tarde). Con la zona de la app en Europe/Madrid la hora_limite
        // debe representar HOY 12:50 en Madrid (UTC+2 en verano → 10:50Z).
        $this->travelTo(Carbon::parse('2026-08-28 10:00:00'));

        $province = Province::create(['name' => 'Kanto']);
        $habitat = Habitat::create(['name' => 'Bosque', 'province_id' => $province->id]);
        $reclutado = $this->crearReclutado();

        $this->post('/exploraciones', [
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
            'level' => 1,
            'return_time' => '12:50',
        ])->assertRedirect();

        /** @var ExploracionActiva $exploracion */
        $exploracion = ExploracionActiva::query()->first();
        $this->assertNotNull($exploracion->hora_limite);

        // hora_limite no tiene cast datetime: se lee como string de la columna time.
        $horaLimite = Carbon::parse($exploracion->hora_limite);
        $this->assertSame('Europe/Madrid', $horaLimite->timezoneName);
        $this->assertSame('12:50', $horaLimite->format('H:i'));

        // Equivalencia sin hardcodear el offset (DST-safe): el instante coincide con
        // hoy 12:50 en Madrid expresado en UTC.
        $esperado = Carbon::today('Europe/Madrid')->setTimeFromTimeString('12:50');
        $this->assertSame(
            $esperado->utc()->format('H:i'),
            $horaLimite->utc()->format('H:i'),
        );

        // E2E: el fin emitido por toActiva() coincide con hoy 12:50 en Madrid.
        /** @var list<array<string, mixed>> $activas */
        $activas = $this->get('/exploraciones')->viewData('activas');
        $this->assertTrue(Carbon::parse($activas[0]['fin'])->equalTo($esperado));
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
