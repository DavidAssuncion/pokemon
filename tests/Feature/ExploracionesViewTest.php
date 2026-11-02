<?php

declare(strict_types=1);

namespace Tests\Feature;

use Src\Exploraciones\Presentation\DiarioExploracion;
use Src\Exploraciones\Presentation\ResultadoExploracionVista;
use Tests\TestCase;

class ExploracionesViewTest extends TestCase
{
    /**
     * El contrato completo del backend (activas + terminadas) renderiza la página
     * con cards, ficha del explorador, diario, hitos y resumen de resultados.
     */
    public function test_exploraciones_view_renders_active_and_finished_explorations(): void
    {
        $view = $this->view('exploraciones.index', [
            'activas' => [
                $this->activa([
                    'id' => 34,
                    'habitat' => 'Caverna Gélida',
                    'habitat_id' => 13,
                    'nivel' => 1,
                    'progreso' => 45,
                    'bitacora' => [
                        ['tipo' => 'pokemon', 'pokemon_id' => 19, 'nombre' => 'Rattata', 'timestamp' => '2026-08-27T12:37:12Z'],
                        ['tipo' => 'caramelo_familia', 'nombre' => 'Rattata', 'cantidad' => 2, 'timestamp' => '2026-08-27T12:40:00Z'],
                        ['tipo' => 'caramelo_ev', 'stat' => 2, 'stat_nombre' => 'Ataque', 'cantidad' => 1, 'timestamp' => '2026-08-27T12:42:31Z'],
                    ],
                ]),
                $this->activa([
                    'id' => 35,
                    'habitat' => 'Lago Místico',
                    'habitat_id' => 7,
                    'nivel' => 3,
                    'estado' => 'volviendo',
                    'progreso' => 90,
                ]),
            ],
            'terminadas' => [
                $this->terminada([
                    'id' => 33,
                    'habitat' => 'Lago Místico',
                    'nivel' => 2,
                    'resultado' => [
                        'avistados' => [
                            ['pokemon_id' => 19, 'nombre' => 'Rattata'],
                            ['pokemon_id' => 129, 'nombre' => 'Magikarp'],
                        ],
                        'capturados' => [
                            ['pokemon_id' => 19, 'nombre' => 'Rattata', 'cantidad' => 3],
                        ],
                        'caramelos_familia' => [
                            ['evolution_chain_id' => 1, 'nombre' => 'Rattata', 'cantidad' => 12],
                        ],
                        'caramelos_ev' => [
                            ['stat' => 2, 'stat_nombre' => 'Ataque', 'stat_slug' => 'atk', 'cantidad' => 8],
                        ],
                        'caramelos_tipo' => [
                            ['tipo' => 'Fuego', 'slug' => 'fuego', 'cantidad' => 5],
                        ],
                        'exp' => 250,
                    ],
                ]),
            ],
        ]);

        $view->assertSee('Exploraciones', false);
        $view->assertSee('Cada explorador vive su propia expedición', false);

        // Activas: ficha del explorador + badges + progreso + tiempos
        $view->assertSee('Rex', false);
        $view->assertSee('Caverna Gélida', false);
        $view->assertSee('Nivel 1', false);
        $view->assertSee('Explorando', false);
        $view->assertSee('Volviendo', false);
        $view->assertSee('style="width: 45%"', false);
        $view->assertSee('style="width: 90%"', false);
        $view->assertSee('Inicio:', false);
        $view->assertSee('Vuelta:', false);
        $view->assertSee('Fin:', false);

        // Diario / bitácora
        $view->assertSee('Bitácora', false);
        $view->assertSee('Encontraste un', false);
        $view->assertSee('Rattata', false);
        $view->assertSee('Caramelos de', false);
        $view->assertSee('Caramelo EV', false);
        $view->assertSee('/images/iconos_webp/19.webp', false);

        // Terminadas: resumen de resultados + cerrar
        $view->assertSee('Cerrar resultados', false);
        $view->assertSee('/exploraciones/${id}/cerrar', false);
        // "Avistados" ya NO se renderiza (bloque eliminado)
        $view->assertDontSee('Avistados');
        // Capturados y caramelos se agrupan en «Recompensas obtenidas».
        $view->assertSee('Recompensas obtenidas', false);
        $view->assertSee('Capturados', false);
        $view->assertSee('×3', false);
        // Recompensas: caramelos familia/EV/tipo en formato compacto
        $view->assertSee('×12', false);
        $view->assertSee('Ataque', false);
        $view->assertSee('×8', false);
        $view->assertSee('Fuego', false);
        $view->assertSee('×5', false);
        // EXP de cuenta en el header
        $view->assertSee('+250 EXP cuenta', false);
    }

    /**
     * Sin exploraciones activas ni resultados pendientes se muestran los empty states.
     */
    public function test_exploraciones_view_renders_empty_states(): void
    {
        $view = $this->view('exploraciones.index', [
            'activas' => [],
            'terminadas' => [],
        ]);

        $view->assertSee('No hay exploraciones activas', false);
        $view->assertSee('Envía a un explorador desde un', false);
        $view->assertSee('href="/habitats"', false);
        $view->assertSee('No hay resultados pendientes de revisar', false);
    }

    /**
     * El banner de procesamiento se muestra con role="status" y avisa del
     * reintento automático y del corte por tiempo.
     */
    public function test_exploraciones_view_renders_processing_banner(): void
    {
        $view = $this->view('exploraciones.index', [
            'activas' => [],
            'terminadas' => [],
            'avisoProcesamiento' => true,
            'agotadoPorTiempo' => true,
        ]);

        $view->assertSee('role="status"', false);
        $view->assertSee('No se pudieron actualizar todas las expediciones; se reintentará automáticamente.', false);
        $view->assertSee('Se actualizaron las primeras expediciones; recarga para continuar.', false);
    }

    /**
     * El botón rojo "Recoger resultados" solo aparece para exploraciones indefinidas
     * y dispara POST a /exploraciones/{id}/recoger.
     */
    public function test_exploraciones_view_shows_recoger_button_only_for_indefinite(): void
    {
        $view = $this->view('exploraciones.index', [
            'activas' => [
                $this->activa([
                    'id' => 34,
                    'habitat' => 'Caverna Gélida',
                    'habitat_id' => 13,
                    'nivel' => 1,
                    'indefinido' => true,
                    'duracion_horas' => null,
                    'inicio_vuelta' => null,
                    'fin' => null,
                    'progreso' => 10,
                ]),
            ],
            'terminadas' => [],
        ]);

        $view->assertSee('Recoger resultados', false);
        $view->assertSee('/exploraciones/${id}/recoger', false);

        // Sin indefinidas el botón no aparece
        $sinIndefinidas = $this->view('exploraciones.index', [
            'activas' => [
                $this->activa([
                    'id' => 35,
                    'habitat' => 'Lago Místico',
                    'habitat_id' => 7,
                    'nivel' => 2,
                    'progreso' => 20,
                ]),
            ],
            'terminadas' => [],
        ]);

        $sinIndefinidas->assertDontSee('Recoger resultados');
    }

    /**
     * La vista es defensiva: si el backend omite claves (bitácora sin stat_nombre,
     * resultado sin capturados) o entrega arrays vacíos, sigue renderizando.
     */
    public function test_exploraciones_view_is_defensive_with_partial_contract(): void
    {
        $view = $this->view('exploraciones.index', [
            'activas' => [
                $this->activa([
                    'id' => 34,
                    'habitat' => 'Caverna Gélida',
                    'habitat_id' => 13,
                    'nivel' => 1,
                    'progreso' => 45,
                    'bitacora' => [
                        // Sin stat_nombre → fallback JS statName(2)
                        ['tipo' => 'caramelo_ev', 'stat' => 2, 'cantidad' => 1, 'timestamp' => '2026-08-27T12:42:31Z'],
                        ['tipo' => 'otro', 'timestamp' => '2026-08-27T12:45:00Z'],
                    ],
                ]),
            ],
            'terminadas' => [
                $this->terminada([
                    'id' => 33,
                    'habitat' => 'Lago Místico',
                    'nivel' => 2,
                    'resultado' => [
                        'avistados' => [],
                        'capturados' => [],
                        'caramelos_familia' => [],
                        'caramelos_ev' => [],
                        'exp' => 0,
                    ],
                ]),
                $this->terminada([
                    'id' => 32,
                    'habitat' => 'Bosque',
                    'nivel' => 1,
                    'resultado' => [],
                ]),
            ],
        ]);

        // Fallback de nombre de stat y evento desconocido
        $view->assertSee('statName(2)', false);
        $view->assertSee('Evento: otro', false);
        // Bloques vacíos omitidos y EXP 0 visible
        $view->assertSee('+0 EXP', false);
        $view->assertSee('Sin resultados', false);
    }

    /**
     * Los caramelos de familia/EV/tipo usan el formato compacto unificado:
     * imagen w-12 + badge circular ámbar ×N + nombre debajo. Con fallback al
     * placeholder si falta pokemon_id/stat_slug y al nombre vía statName si falta.
     */
    public function test_exploraciones_view_unifies_candy_compact_format(): void
    {
        $view = $this->view('exploraciones.index', [
            'activas' => [],
            'terminadas' => [
                $this->terminada([
                    'id' => 33,
                    'habitat' => 'Lago Místico',
                    'nivel' => 2,
                    'resultado' => [
                        // avistados presentes → debe IGNORARSE sin error
                        'avistados' => [
                            ['pokemon_id' => 19, 'nombre' => 'Rattata'],
                        ],
                        'capturados' => [],
                        'caramelos_familia' => [
                            // sin pokemon_id ni nombre → placeholder + sin nombre
                            ['evolution_chain_id' => 5, 'nombre' => null, 'pokemon_id' => null, 'cantidad' => 7],
                            ['evolution_chain_id' => 1, 'nombre' => 'Rattata', 'pokemon_id' => 19, 'cantidad' => 12],
                        ],
                        'caramelos_ev' => [
                            // sin stat_slug ni stat_nombre → fallback statName + placeholder
                            ['stat' => 3, 'stat_nombre' => null, 'stat_slug' => null, 'cantidad' => 4],
                        ],
                        'caramelos_tipo' => [
                            ['tipo' => 'Agua', 'slug' => 'agua', 'cantidad' => 2],
                        ],
                        'exp' => 100,
                    ],
                ]),
            ],
        ]);

        // No se renderiza Avistados (defensivo)
        $view->assertDontSee('Avistados');

        // Caramelo familia con datos → imagen + badge amber + nombre
        $view->assertSee('/images/candy_pokemon/19.webp', false);
        $view->assertSee('Rattata', false);
        $view->assertSee('×12', false);
        // Caramelo familia sin pokemon_id → placeholder y badge
        $view->assertSee('/images/candy_pokemon/0.webp', false);
        $view->assertSee('×7', false);
        // Caramelo EV sin slug → placeholder + badge ámbar; nombre fallback statName(3)
        $view->assertSee('/images/candy_ev/.webp', false);
        $view->assertSee('statName(3)', false);
        $view->assertSee('×4', false);
        // Caramelo tipo → ruta candy_type + nombre
        $view->assertSee('/images/candy_type/agua.webp', false);
        $view->assertSee('Agua', false);
        $view->assertSee('×2', false);

        // No se muestran etiquetas de sección de caramelos
        $view->assertDontSee('Caramelos de familia');
        $view->assertDontSee('Caramelos EV');
        $view->assertDontSee('Caramelos de tipo');
    }

    /**
     * Sin capturados, las recompensas ocupan toda la anchura; sin recompensas,
     * solo se muestra el bloque de capturados; sin ambos, "Sin resultados".
     */
    public function test_exploraciones_view_layout_handles_empty_candy_sides(): void
    {
        // Solo capturados (recompensas vacías → no se renderizan)
        $soloCapturados = $this->view('exploraciones.index', [
            'activas' => [],
            'terminadas' => [
                $this->terminada([
                    'id' => 31,
                    'habitat' => 'Bosque',
                    'nivel' => 1,
                    'resultado' => [
                        'capturados' => [
                            ['pokemon_id' => 19, 'nombre' => 'Rattata', 'cantidad' => 2],
                        ],
                        'caramelos_familia' => [],
                        'caramelos_ev' => [],
                        'caramelos_tipo' => [],
                        'exp' => 30,
                    ],
                ]),
            ],
        ]);
        $soloCapturados->assertSee('Recompensas obtenidas', false);
        $soloCapturados->assertSee('Capturados', false);
        $soloCapturados->assertSee('Rattata', false);
        $soloCapturados->assertSee('×2', false);
        // Solo capturados (sin derrotados ni pérdidas) → columna a col-span-3
        $soloCapturados->assertSee('lg:col-span-3', false);
        $soloCapturados->assertDontSee('lg:col-span-4', false);
        $soloCapturados->assertDontSee('Agua', false);

        // Solo recompensas (sin capturados) → recompensas a col-span-4
        $soloRecompensas = $this->view('exploraciones.index', [
            'activas' => [],
            'terminadas' => [
                $this->terminada([
                    'id' => 30,
                    'habitat' => 'Caverna',
                    'nivel' => 1,
                    'resultado' => [
                        'capturados' => [],
                        'caramelos_familia' => [
                            ['evolution_chain_id' => 1, 'nombre' => 'Rattata', 'pokemon_id' => 19, 'cantidad' => 3],
                        ],
                        'caramelos_ev' => [],
                        'caramelos_tipo' => [],
                        'exp' => 10,
                    ],
                ]),
            ],
        ]);
        $soloRecompensas->assertSee('Recompensas obtenidas', false);
        $soloRecompensas->assertSee('Rattata', false);
        $soloRecompensas->assertSee('lg:col-span-3', false);
        $soloRecompensas->assertDontSee('lg:col-span-4', false);
        $soloRecompensas->assertDontSee('Capturados');
    }

    /**
     * La tarjeta terminada expone 'derrotados' (ids expandidos por derrota).
     * Con capturados y recompensas: derrotados=1, capturados=1, recompensas=2.
     * Sin capturados: derrotados=1, recompensas=3. Solo derrotados: col-span-4.
     */
    public function test_exploraciones_view_renders_derrotados_grid(): void
    {
        // Derrotados + capturados + recompensas → 1/1/2
        $completo = $this->view('exploraciones.index', [
            'activas' => [],
            'terminadas' => [
                $this->terminada([
                    'id' => 71,
                    'habitat' => 'Cueva',
                    'nivel' => 1,
                    'derrotados' => [19, 19, 129],
                    'resultado' => [
                        'capturados' => [
                            ['pokemon_id' => 25, 'nombre' => 'Pikachu', 'cantidad' => 1],
                        ],
                        'caramelos_familia' => [
                            ['evolution_chain_id' => 1, 'nombre' => 'Rattata', 'pokemon_id' => 19, 'cantidad' => 2],
                        ],
                        'caramelos_ev' => [],
                        'caramelos_tipo' => [],
                        'exp' => 40,
                    ],
                ]),
            ],
        ]);
        $completo->assertSee('Derrotados', false);
        $completo->assertSee('/images/iconos_webp/19.webp', false);
        $completo->assertSee('/images/iconos_webp/129.webp', false);
        $completo->assertSee('×2', false);
        $completo->assertSee('lg:col-span-2', false);
        $completo->assertSee('Pikachu', false);

        // Derrotados + recompensas (sin capturados) → derrotados=1, recompensas=3
        $sinCapturas = $this->view('exploraciones.index', [
            'activas' => [],
            'terminadas' => [
                $this->terminada([
                    'id' => 72,
                    'habitat' => 'Lago',
                    'nivel' => 1,
                    'derrotados' => [25],
                    'resultado' => [
                        'capturados' => [],
                        'caramelos_familia' => [],
                        'caramelos_ev' => [
                            ['stat' => 2, 'stat_nombre' => 'Ataque', 'stat_slug' => 'atk', 'cantidad' => 4],
                        ],
                        'caramelos_tipo' => [],
                        'exp' => 10,
                    ],
                ]),
            ],
        ]);
        $sinCapturas->assertSee('Derrotados', false);
        $sinCapturas->assertSee('Recompensas obtenidas', false);
        $sinCapturas->assertSee('lg:col-span-2', false);
        $sinCapturas->assertSee('/images/iconos_webp/25.webp', false);
        $sinCapturas->assertDontSee('Capturados');

        // Solo derrotados → col-span-4
        $soloDerrotados = $this->view('exploraciones.index', [
            'activas' => [],
            'terminadas' => [
                $this->terminada([
                    'id' => 73,
                    'habitat' => 'Bosque',
                    'nivel' => 1,
                    'derrotados' => [129],
                    'resultado' => [
                        'capturados' => [],
                        'caramelos_familia' => [],
                        'caramelos_ev' => [],
                        'caramelos_tipo' => [],
                        'exp' => 0,
                    ],
                ]),
            ],
        ]);
        $soloDerrotados->assertSee('Derrotados', false);
        $soloDerrotados->assertSee('lg:col-span-3', false);
        $soloDerrotados->assertDontSee('lg:col-span-4', false);
        $soloDerrotados->assertDontSee('Capturados');

        // Sin derrotados → no se renderiza el bloque
        $sinDerrotados = $this->view('exploraciones.index', [
            'activas' => [],
            'terminadas' => [
                $this->terminada([
                    'id' => 74,
                    'habitat' => 'Playa',
                    'nivel' => 1,
                    'resultado' => [
                        'capturados' => [],
                        'caramelos_familia' => [],
                        'caramelos_ev' => [],
                        'caramelos_tipo' => [],
                        'exp' => 5,
                    ],
                ]),
            ],
        ]);
        $sinDerrotados->assertDontSee('Derrotados');
        $sinDerrotados->assertDontSee('Recompensas obtenidas');
    }

    /**
     * Los nuevos tipos de evento de la bitácora activa (huida, emboscada,
     * contratiempo, retirada, grupo y hallazgo) se renderizan con textos
     * narrativos e iconos.
     */
    public function test_exploraciones_view_renders_risk_bitacora_event_types(): void
    {
        $view = $this->view('exploraciones.index', [
            'activas' => [
                $this->activa([
                    'id' => 40,
                    'habitat' => 'Bosque',
                    'habitat_id' => 1,
                    'nivel' => 1,
                    'progreso' => 30,
                    'bitacora' => [
                        ['tipo' => 'huida', 'pokemon_id' => 19, 'nombre' => 'Rattata', 'timestamp' => '2026-08-30T10:05:00Z'],
                        ['tipo' => 'emboscada', 'resolucion' => 'combate', 'duration_loss' => 10, 'pokemon_ids' => [19, 129], 'timestamp' => '2026-08-30T10:10:00Z'],
                        ['tipo' => 'emboscada', 'resolucion' => 'sin_combate', 'timestamp' => '2026-08-30T10:12:00Z'],
                        ['tipo' => 'contratiempo', 'subtype' => 'desorientacion', 'duration_loss' => 15, 'timestamp' => '2026-08-30T10:15:00Z'],
                        ['tipo' => 'retirada', 'reason' => 'el equipo sufrió demasiadas bajas', 'timestamp' => '2026-08-30T10:20:00Z'],
                        ['tipo' => 'grupo', 'pokemon_ids' => [25, 26], 'timestamp' => '2026-08-30T10:25:00Z'],
                        ['tipo' => 'hallazgo', 'subtype' => 'caramelo_familia', 'pokemon_id' => 19, 'nombre' => 'Rattata', 'cantidad' => 2, 'timestamp' => '2026-08-30T10:30:00Z'],
                        ['tipo' => 'hallazgo', 'subtype' => 'caramelo_ev', 'stat' => 2, 'cantidad' => 1, 'timestamp' => '2026-08-30T10:35:00Z'],
                        ['tipo' => 'hallazgo', 'subtype' => 'caramelo_tipo', 'tipo_nombre' => 'Fuego', 'slug' => 'fuego', 'cantidad' => 3, 'timestamp' => '2026-08-30T10:40:00Z'],
                        ['tipo' => 'hallazgo', 'subtype' => 'caramelo_tipo', 'slug' => 'agua', 'cantidad' => 1, 'timestamp' => '2026-08-30T10:45:00Z'],
                        ['tipo' => 'encuentro', 'subtype' => 'normal', 'pokemon_id' => 19, 'nombre' => 'Rattata', 'resolucion' => 'victoria', 'duration_loss' => 0, 'timestamp' => '2026-08-30T10:50:00Z'],
                        ['tipo' => 'encuentro', 'subtype' => 'excepcional', 'pokemon_id' => 129, 'nombre' => 'Magikarp', 'resolucion' => 'victoria_con_coste', 'duration_loss' => 12, 'timestamp' => '2026-08-30T10:55:00Z'],
                        ['tipo' => 'encuentro', 'subtype' => 'grupo', 'pokemon_id' => 25, 'nombre' => 'Pikachu', 'resolucion' => 'derrota', 'duration_loss' => 10, 'timestamp' => '2026-08-30T11:00:00Z'],
                        ['tipo' => 'neutral', 'timestamp' => '2026-08-30T11:05:00Z'],
                    ],
                ]),
            ],
            'terminadas' => [],
        ]);

        // huida
        $view->assertSee('huye antes de que comience el combate', false);
        $view->assertSee('Rattata', false);
        $view->assertSee('/images/iconos_webp/19.webp', false);
        // emboscada: título + subtítulo según resolución + mini-iconos
        $view->assertSee('¡Emboscada!', false);
        $view->assertSee('repele el ataque (-10 min)', false);
        $view->assertSee('escapa perdiendo tiempo', false);
        $view->assertSee('/images/iconos_webp/129.webp', false);
        // contratiempo (desorientación)
        $view->assertSee('pierde el rastro. -15 min', false);
        // retirada
        // Blade aún hardcodea el sujeto de retirada (frontend pendiente):
        // se valida el texto sin fijar "El equipo"/"El explorador".
        $view->assertSee('se retira:', false);
        $view->assertSee('demasiadas bajas', false);
        // grupo + mini-iconos
        $view->assertSee('se encuentra con un grupo salvaje', false);
        $view->assertSee('/images/iconos_webp/25.webp', false);
        $view->assertSee('/images/iconos_webp/26.webp', false);
        // hallazgo familia: candy_imagen (sprite de especie) antes que candy_src
        $view->assertSee('Caramelos de', false);
        $view->assertSee('/images/iconos_webp/19.webp', false);
        $view->assertDontSee('src="/images/candy_pokemon/19.webp"', false);
        $view->assertSee('×2', false);
        // hallazgo EV (sin stat_nombre → fallback JS statName)
        $view->assertSee('Caramelo EV', false);
        $view->assertSee('statName(2)', false);
        $view->assertSee('×1', false);
        // hallazgo tipo (tipo_nombre) + derivado del slug
        $view->assertSee('Caramelo de tipo', false);
        $view->assertSee('/images/candy_type/fuego.webp', false);
        $view->assertSee('×3', false);
        $view->assertSee('Agua', false);
        $view->assertSee('/images/candy_type/agua.webp', false);
        // encuentro normal + victoria
        $view->assertSee('Encuentro', false);
        $view->assertSee('· Victoria', false);
        $view->assertSee('/images/iconos_webp/19.webp', false);
        // encuentro excepcional + victoria con coste + duration_loss
        $view->assertSee('¡Encuentro excepcional!', false);
        $view->assertSee('· Victoria con coste', false);
        $view->assertSee('(-12 min)', false);
        $view->assertSee('/images/iconos_webp/129.webp', false);
        // encuentro grupo + derrota (sin claves de PV → sin detalle adicional)
        $view->assertSee('Grupo salvaje', false);
        $view->assertSee('· Derrota', false);
        $view->assertSee('/images/iconos_webp/25.webp', false);
        $view->assertDontSee('Entró con', false);
        // neutral → texto + SVG informativo (sin imagen de pokémon)
        $view->assertSee('Sin incidencias', false);
        $view->assertDontSee('Derrotados');
    }

    /**
     * El diario usa `candy_imagen` en las dos ramas de caramelo de familia
     * (evento raíz y hallazgo) y cae al placeholder cuando no hay imagen.
     */
    public function test_diario_usa_candy_imagen_y_fallback_en_caramelo_familia(): void
    {
        $view = $this->view('exploraciones.index', [
            'activas' => [
                $this->activa([
                    'id' => 41,
                    'bitacora' => [
                        ['tipo' => 'caramelo_familia', 'pokemon_id' => 81, 'nombre' => 'Magnemite', 'cantidad' => 2, 'timestamp' => '2026-08-30T11:10:00Z'],
                        ['tipo' => 'hallazgo', 'subtype' => 'caramelo_familia', 'pokemon_id' => 81, 'nombre' => 'Magnemite', 'cantidad' => 3, 'timestamp' => '2026-08-30T11:11:00Z'],
                        ['tipo' => 'caramelo_familia', 'cantidad' => 1, 'timestamp' => '2026-08-30T11:12:00Z'],
                    ],
                ]),
            ],
            'terminadas' => [],
        ]);

        // Ambas ramas renderizan el sprite de especie que envía candy_imagen.
        $view->assertSee('src="/images/iconos_webp/81.webp"', false);
        // El onerror encadena candy_src antes del placeholder.
        $view->assertSee('src=&#039;/images/candy_pokemon/81.webp&#039;', false);
        $view->assertSee('src=&#039;/images/candy_pokemon/0.webp&#039;', false);
        $view->assertDontSee('src="/images/candy_pokemon/81.webp"', false);
        $view->assertSee('Caramelos de', false);
        $view->assertSee('Magnemite', false);
        $view->assertSee('×2', false);
        $view->assertSee('×3', false);
        // Sin candy_imagen ni candy_src → placeholder explícito.
        $view->assertSee('src="/images/candy_pokemon/0.webp"', false);
    }

    /**
     * `detalle_derrota` se imprime tal cual bajo el título del combate, y el
     * evento raíz `derrota` muestra `reason_texto`, nunca el motivo técnico.
     */
    public function test_diario_muestra_detalle_derrota_y_reason_texto(): void
    {
        $log = ['--- Ronda 1 ---', '--- Ronda 2 ---', '--- Ronda 3 ---', '--- Ronda 4 ---'];

        $view = $this->view('exploraciones.index', [
            'activas' => [
                $this->activa([
                    'id' => 42,
                    'bitacora' => [
                        [
                            'tipo' => 'encuentro',
                            'subtype' => 'normal',
                            'pokemon_id' => 436,
                            'nombre' => 'Bronzor',
                            'resolucion' => 'derrota',
                            'hp_inicial' => 39,
                            'hp_max' => 39,
                            'hp_final' => 0,
                            'rival_hp_inicial' => 34,
                            'rival_hp_final' => 12,
                            'rival_hp_max' => 34,
                            'log' => $log,
                            'timestamp' => '2026-08-30T11:20:00Z',
                        ],
                        [
                            'tipo' => 'emboscada',
                            'pokemon_id' => 436,
                            'nombre' => 'Bronzor',
                            'resolucion' => 'derrota',
                            'hp_inicial' => 39,
                            'hp_max' => 39,
                            'hp_final' => 0,
                            'rival_hp_inicial' => 34,
                            'rival_hp_final' => 12,
                            'rival_hp_max' => 34,
                            'log' => $log,
                            'timestamp' => '2026-08-30T11:25:00Z',
                        ],
                        ['tipo' => 'derrota', 'reason' => 'explorador_debilitado', 'timestamp' => '2026-08-30T11:30:00Z'],
                    ],
                ]),
            ],
            'terminadas' => [],
        ]);

        $view->assertSee('Entró con 39/39 PS tras 4 rondas; a Bronzor le quedaban 12/34 PS.', false);
        $view->assertSee('(se quedó sin PS)', false);
        $view->assertDontSee('explorador_debilitado', false);
    }

    /**
     * Los resultados terminados incluyen badge de categoría con color por grado,
     * resumen de incidentes y línea de tiempo (efectivos/perdidos).
     */
    public function test_exploraciones_view_renders_result_category_incidents_and_timeline(): void
    {
        $view = $this->view('exploraciones.index', [
            'activas' => [],
            'terminadas' => [
                $this->terminada([
                    'id' => 50,
                    'habitat' => 'Bosque',
                    'nivel' => 2,
                    'resultado' => [
                        'resultado' => 'exito_parcial',
                        'duration_real' => 105,
                        'tiempo_perdido' => 20,
                        'incidentes' => [
                            'encuentros' => 12,
                            'victorias' => 8,
                            'huidas' => 2,
                            'emboscadas' => 1,
                            'contratiempos' => 3,
                        ],
                        'capturados' => [],
                        'caramelos_familia' => [],
                        'caramelos_ev' => [],
                        'caramelos_tipo' => [],
                        'exp' => 50,
                    ],
                ]),
            ],
        ]);

        $view->assertSee('Éxito parcial', false);
        $view->assertSee('Encuentros 12', false);
        $view->assertSee('Victorias 8', false);
        $view->assertSee('Huidas 2', false);
        $view->assertSee('Emboscadas 1', false);
        $view->assertSee('Contratiempos 3', false);
        // 105 - 20 = 85 min efectivos
        $view->assertSee('85 min efectivos · 20 min perdidos', false);
    }

    /**
     * Compatibilidad con exploraciones antiguas: sin campos de riesgo (resultado,
     * incidentes, duration_real, tiempo_perdido) no se renderiza el resumen y la
     * bitácora legacy sigue intacta.
     */
    public function test_exploraciones_view_is_defensive_without_risk_fields(): void
    {
        $view = $this->view('exploraciones.index', [
            'activas' => [
                $this->activa([
                    'id' => 60,
                    'habitat' => 'Bosque',
                    'habitat_id' => 1,
                    'nivel' => 1,
                    'progreso' => 10,
                    'bitacora' => [
                        ['tipo' => 'pokemon', 'pokemon_id' => 19, 'nombre' => 'Rattata', 'timestamp' => '2026-08-30T10:05:00Z'],
                    ],
                ]),
            ],
            'terminadas' => [
                $this->terminada([
                    'id' => 61,
                    'habitat' => 'Bosque',
                    'nivel' => 1,
                    'resultado' => [
                        'capturados' => [],
                        'caramelos_familia' => [],
                        'caramelos_ev' => [],
                        'caramelos_tipo' => [],
                        'exp' => 0,
                    ],
                ]),
            ],
        ]);

        // Bitácora legacy intacta
        $view->assertSee('Encontraste un', false);
        $view->assertSee('Rattata', false);
        // Sin resumen de riesgo: no hay badge, incidentes ni línea de tiempo
        $view->assertDontSee('Éxito parcial');
        $view->assertDontSee('min efectivos');
        $view->assertDontSee('Encuentros');
        $view->assertSee('+0 EXP', false);
    }

    /**
     * El partial de pérdidas muestra imagen, tipo, nombre y cantidad, y deja
     * el ID solo en el `title` para conservar la trazabilidad.
     */
    public function test_perdidas_partial_renderiza_imagen_nombre_y_cantidad(): void
    {
        $view = $this->view('exploraciones._perdidas', [
            'perdidas' => [
                [
                    'tipo' => 'familia',
                    'tipo_label' => 'Familia',
                    'id' => 299,
                    'label' => 'Nosepass',
                    'imagen' => '/images/candy_pokemon/299.webp',
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
            ],
            'motivo' => 'Tu explorador cayó debilitado en combate y perdió parte del botín.',
        ]);

        $view->assertSee('Recompensas perdidas', false);
        $view->assertSee('grayscale', false);
        $view->assertSee('Tu explorador cayó debilitado en combate y perdió parte del botín.', false);
        $view->assertSee('src="/images/candy_pokemon/299.webp"', false);
        $view->assertSee('alt="Nosepass"', false);
        $view->assertSee('Familia', false);
        $view->assertSee('Nosepass', false);
        $view->assertSee('×2', false);
        $view->assertSee('src="/images/candy_ev/def.webp"', false);
        $view->assertSee('alt="Defensa"', false);
        $view->assertSee('EV', false);
        $view->assertSee('Defensa', false);
        $view->assertSee('×1', false);
        $view->assertSee('title="Familia #299"', false);
        $view->assertSee('title="EV #3"', false);
    }

    /**
     * Sin pérdidas el partial no renderiza ningún bloque.
     */
    public function test_perdidas_partial_no_renderiza_nada_sin_perdidas(): void
    {
        $this->view('exploraciones._perdidas', ['perdidas' => []])
            ->assertDontSee('Recompensas perdidas', false);
    }

    /**
     * Con pérdidas pero sin motivo no se renderiza el párrafo explicativo.
     */
    public function test_perdidas_partial_sin_motivo_no_renderiza_parrafo(): void
    {
        $view = $this->view('exploraciones._perdidas', [
            'perdidas' => [
                [
                    'tipo' => 'familia',
                    'tipo_label' => 'Familia',
                    'id' => 299,
                    'label' => 'Nosepass',
                    'imagen' => '/images/candy_pokemon/299.webp',
                    'cantidad_perdida' => 2,
                ],
            ],
        ]);

        $view->assertSee('Recompensas perdidas', false);
        $view->assertDontSee('Tu explorador cayó debilitado en combate y perdió parte del botín.', false);
    }

    /**
     * Contrato mínimo de una exploración activa: incluye la ficha del explorador
     * y el diario transformado a partir de la bitácora.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function activa(array $overrides = []): array
    {
        $estado = (string) ($overrides['estado'] ?? 'explorando');
        $bitacora = is_array($overrides['bitacora'] ?? null) ? $overrides['bitacora'] : [];

        return array_merge([
            'id' => 34,
            'equipo' => 'Equipo A',
            'reclutado' => 'Rex',
            'habitat' => 'Caverna Gélida',
            'habitat_id' => 13,
            'nivel' => 1,
            'min_lvl' => null,
            'requiere_nivel' => false,
            'indefinido' => false,
            'duracion_horas' => 4,
            'inicio' => '2026-08-27T12:36:51Z',
            'inicio_vuelta' => '2026-08-27T15:36:51Z',
            'fin' => '2026-08-27T16:36:51Z',
            'estado' => $estado,
            'estado_label' => $estado === 'volviendo' ? 'Volviendo' : 'Explorando',
            'estado_clase' => $estado === 'volviendo'
                ? 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400'
                : 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
            'progreso' => 45,
            'restante_label' => '2 h',
            'explorador' => $this->explorador(),
            'diario' => $this->diario($bitacora),
            'bitacora' => [],
        ], $overrides);
    }

    /**
     * Contrato mínimo de una exploración terminada, normalizado por el
     * presentador de resultados (añade `vista`, `hitos`, spans y flags).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function terminada(array $overrides = []): array
    {
        $bitacora = is_array($overrides['bitacora'] ?? null) ? $overrides['bitacora'] : [];

        return ResultadoExploracionVista::toView(array_merge([
            'id' => 33,
            'equipo' => 'Equipo B',
            'reclutado' => 'Rex',
            'habitat' => 'Lago Místico',
            'nivel' => 2,
            'min_lvl' => null,
            'requiere_nivel' => false,
            'explorador' => $this->explorador(),
            'diario' => $this->diario($bitacora),
            'exp_reclutado' => 0,
            'derrotados' => [],
            'resultado' => [
                'capturados' => [],
                'caramelos_familia' => [],
                'caramelos_ev' => [],
                'caramelos_tipo' => [],
                'exp' => 0,
            ],
        ], $overrides));
    }

    /**
     * Ficha mínima del explorador consumida por `_explorador.blade.php`.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function explorador(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'nombre' => 'Rex',
            'pokemon_id' => 25,
            'icono' => '/images/iconos_webp/25.webp',
            'nivel' => 5,
            'exp_total' => 1000,
            'exp_tipos' => ['electrico' => 1000],
            'es_shiny' => false,
            'rol' => 'COMBATIENTE',
            'rol_label' => 'Combatiente',
            'tipos' => [
                ['nombre' => 'Eléctrico', 'slug' => 'electrico'],
            ],
            'hp_actual' => 40,
            'hp_max' => 50,
            'hp_pct' => 80,
            'hp_estado' => 'sano',
            'hp_label' => '40 / 50 PS (80%)',
            'hp_clase' => 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
            'barreras' => [],
            'animo' => ['texto' => 'Con ganas de explorar.', 'tono' => 'positivo'],
            'progreso_nivel' => 40,
        ], $overrides);
    }

    /**
     * Diario normalizado a partir de la bitácora. Sin eventos devuelve el
     * contrato vacío que la vista sabe renderizar.
     *
     * @param  list<array<string, mixed>>  $bitacora
     * @return array{total: int, eventos: list<array<string, mixed>>, grupos: list<array<string, mixed>>, hitos: list<array<string, mixed>>}
     */
    private function diario(array $bitacora): array
    {
        if ($bitacora === []) {
            return [
                'total' => 0,
                'eventos' => [],
                'grupos' => [],
                'hitos' => [],
            ];
        }

        return DiarioExploracion::toView(['bitacora' => $bitacora], [], 'Rex');
    }
}
