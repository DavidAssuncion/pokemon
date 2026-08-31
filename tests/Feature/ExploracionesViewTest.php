<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class ExploracionesViewTest extends TestCase
{
    /**
     * El contrato completo del backend (activas + terminadas) renderiza la página
     * con cards, badges de estado, barra de progreso, bitácora y resumen de resultados.
     */
    public function test_exploraciones_view_renders_active_and_finished_explorations(): void
    {
        $view = $this->view('exploraciones.index', [
            'activas' => [
                [
                    'id' => 34,
                    'equipo' => 'Equipo A',
                    'habitat' => 'Caverna Gélida',
                    'habitat_id' => 13,
                    'nivel' => 1,
                    'indefinido' => false,
                    'duracion_horas' => 4,
                    'inicio' => '2026-08-27T12:36:51Z',
                    'inicio_vuelta' => '2026-08-27T15:36:51Z',
                    'fin' => '2026-08-27T16:36:51Z',
                    'estado' => 'explorando',
                    'progreso' => 45,
                    'bitacora' => [
                        ['tipo' => 'pokemon', 'pokemon_id' => 19, 'nombre' => 'Rattata', 'timestamp' => '2026-08-27T12:37:12Z'],
                        ['tipo' => 'caramelo_familia', 'nombre' => 'Rattata', 'cantidad' => 2, 'timestamp' => '2026-08-27T12:40:00Z'],
                        ['tipo' => 'caramelo_ev', 'stat' => 2, 'stat_nombre' => 'Ataque', 'cantidad' => 1, 'timestamp' => '2026-08-27T12:42:31Z'],
                    ],
                ],
                [
                    'id' => 35,
                    'equipo' => 'Equipo C',
                    'habitat' => 'Lago Místico',
                    'habitat_id' => 7,
                    'nivel' => 3,
                    'indefinido' => false,
                    'duracion_horas' => 4,
                    'inicio' => '2026-08-27T10:00:00Z',
                    'inicio_vuelta' => '2026-08-27T12:00:00Z',
                    'fin' => '2026-08-27T14:00:00Z',
                    'estado' => 'volviendo',
                    'progreso' => 90,
                    'bitacora' => [],
                ],
            ],
            'terminadas' => [
                [
                    'id' => 33,
                    'equipo' => 'Equipo B',
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
                ],
            ],
        ]);

        $view->assertSee('Exploraciones', false);
        $view->assertSee('Gestión de expediciones de tus equipos', false);

        // Activas: card + badges + progreso + tiempos
        $view->assertSee('Equipo A', false);
        $view->assertSee('Caverna Gélida', false);
        $view->assertSee('Nivel 1', false);
        $view->assertSee('Explorando', false);
        $view->assertSee('Volviendo', false);
        $view->assertSee('style="width: 45%"', false);
        $view->assertSee('style="width: 90%"', false);
        $view->assertSee('Inicio:', false);
        $view->assertSee('Vuelta:', false);
        $view->assertSee('Fin:', false);

        // Bitácora
        $view->assertSee('Bitácora', false);
        $view->assertSee('Encontraste un', false);
        $view->assertSee('Rattata', false);
        $view->assertSee('Caramelos de', false);
        $view->assertSee('Caramelo EV', false);
        $view->assertSee('/images/iconos_webp/19.webp', false);

        // Terminadas: resumen de resultados + cerrar
        $view->assertSee('Equipo B', false);
        $view->assertSee('Cerrar resultados', false);
        $view->assertSee('/exploraciones/${id}/cerrar', false);
        // "Avistados" ya NO se renderiza (bloque eliminado)
        $view->assertDontSee('Avistados');
        // Capturados: bloque col 1 intacto (icono + badge verde ×N + nombre)
        $view->assertSee('Capturados', false);
        $view->assertSee('×3', false);
        // Recompensas: caramelos familia/EV/tipo en formato compacto
        $view->assertSee('Rattata', false);
        $view->assertSee('×12', false);
        $view->assertSee('Ataque', false);
        $view->assertSee('×8', false);
        $view->assertSee('Fuego', false);
        $view->assertSee('×5', false);
        // EXP movida al header
        $view->assertSee('+250 EXP', false);
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
        $view->assertSee('Envía a tu equipo desde un', false);
        $view->assertSee('href="/habitats"', false);
        $view->assertSee('No hay resultados pendientes de revisar', false);
    }

    /**
     * El botón rojo "Recoger resultados" solo aparece para exploraciones indefinidas
     * y dispara POST a /exploraciones/{id}/recoger.
     */
    public function test_exploraciones_view_shows_recoger_button_only_for_indefinite(): void
    {
        $view = $this->view('exploraciones.index', [
            'activas' => [
                [
                    'id' => 34,
                    'equipo' => 'Equipo A',
                    'habitat' => 'Caverna Gélida',
                    'habitat_id' => 13,
                    'nivel' => 1,
                    'indefinido' => true,
                    'duracion_horas' => null,
                    'inicio' => '2026-08-27T12:36:51Z',
                    'inicio_vuelta' => null,
                    'fin' => null,
                    'estado' => 'explorando',
                    'progreso' => 10,
                    'bitacora' => [],
                ],
            ],
            'terminadas' => [],
        ]);

        $view->assertSee('Recoger resultados', false);
        $view->assertSee('/exploraciones/${id}/recoger', false);

        // Sin indefinidas el botón no aparece
        $sinIndefinidas = $this->view('exploraciones.index', [
            'activas' => [
                [
                    'id' => 35,
                    'equipo' => 'Equipo C',
                    'habitat' => 'Lago Místico',
                    'habitat_id' => 7,
                    'nivel' => 2,
                    'indefinido' => false,
                    'duracion_horas' => 4,
                    'inicio' => '2026-08-27T10:00:00Z',
                    'inicio_vuelta' => '2026-08-27T12:00:00Z',
                    'fin' => '2026-08-27T14:00:00Z',
                    'estado' => 'explorando',
                    'progreso' => 20,
                    'bitacora' => [],
                ],
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
                [
                    'id' => 34,
                    'equipo' => 'Equipo A',
                    'habitat' => 'Caverna Gélida',
                    'habitat_id' => 13,
                    'nivel' => 1,
                    'indefinido' => false,
                    'duracion_horas' => 4,
                    'inicio' => '2026-08-27T12:36:51Z',
                    'inicio_vuelta' => '2026-08-27T15:36:51Z',
                    'fin' => '2026-08-27T16:36:51Z',
                    'estado' => 'explorando',
                    'progreso' => 45,
                    'bitacora' => [
                        // Sin stat_nombre → fallback JS statName(2)
                        ['tipo' => 'caramelo_ev', 'stat' => 2, 'cantidad' => 1, 'timestamp' => '2026-08-27T12:42:31Z'],
                        ['tipo' => 'otro', 'timestamp' => '2026-08-27T12:45:00Z'],
                    ],
                ],
            ],
            'terminadas' => [
                [
                    'id' => 33,
                    'equipo' => 'Equipo B',
                    'habitat' => 'Lago Místico',
                    'nivel' => 2,
                    'resultado' => [
                        'avistados' => [],
                        'capturados' => [],
                        'caramelos_familia' => [],
                        'caramelos_ev' => [],
                        'exp' => 0,
                    ],
                ],
                [
                    'id' => 32,
                    'equipo' => 'Equipo D',
                    'habitat' => 'Bosque',
                    'nivel' => 1,
                    'resultado' => [],
                ],
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
                [
                    'id' => 33,
                    'equipo' => 'Equipo B',
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
                ],
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
                [
                    'id' => 31,
                    'equipo' => 'Equipo X',
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
                ],
            ],
        ]);
        $soloCapturados->assertSee('Capturados', false);
        $soloCapturados->assertSee('Rattata', false);
        $soloCapturados->assertSee('×2', false);
        // Solo capturados (sin derrotados ni recompensas) → bloque a col-span-4
        $soloCapturados->assertSee('lg:col-span-4', false);
        $soloCapturados->assertDontSee('lg:col-span-3', false);
        $soloCapturados->assertDontSee('Agua', false);

        // Solo recompensas (sin capturados) → recompensas a col-span-4
        $soloRecompensas = $this->view('exploraciones.index', [
            'activas' => [],
            'terminadas' => [
                [
                    'id' => 30,
                    'equipo' => 'Equipo Y',
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
                ],
            ],
        ]);
        $soloRecompensas->assertSee('Rattata', false);
        $soloRecompensas->assertSee('lg:col-span-4', false);
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
                [
                    'id' => 71,
                    'equipo' => 'Equipo Z',
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
                ],
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
                [
                    'id' => 72,
                    'equipo' => 'Equipo W',
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
                ],
            ],
        ]);
        $sinCapturas->assertSee('Derrotados', false);
        $sinCapturas->assertSee('lg:col-span-3', false);
        $sinCapturas->assertSee('/images/iconos_webp/25.webp', false);
        $sinCapturas->assertDontSee('Capturados');

        // Solo derrotados → col-span-4
        $soloDerrotados = $this->view('exploraciones.index', [
            'activas' => [],
            'terminadas' => [
                [
                    'id' => 73,
                    'equipo' => 'Equipo V',
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
                ],
            ],
        ]);
        $soloDerrotados->assertSee('Derrotados', false);
        $soloDerrotados->assertSee('lg:col-span-4', false);
        $soloDerrotados->assertDontSee('Capturados');

        // Sin derrotados → no se renderiza el bloque
        $sinDerrotados = $this->view('exploraciones.index', [
            'activas' => [],
            'terminadas' => [
                [
                    'id' => 74,
                    'equipo' => 'Equipo U',
                    'habitat' => 'Playa',
                    'nivel' => 1,
                    'resultado' => [
                        'capturados' => [],
                        'caramelos_familia' => [],
                        'caramelos_ev' => [],
                        'caramelos_tipo' => [],
                        'exp' => 5,
                    ],
                ],
            ],
        ]);
        $sinDerrotados->assertDontSee('Derrotados');
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
                [
                    'id' => 40,
                    'equipo' => 'Equipo A',
                    'habitat' => 'Bosque',
                    'habitat_id' => 1,
                    'nivel' => 1,
                    'indefinido' => false,
                    'duracion_horas' => 4,
                    'inicio' => '2026-08-30T10:00:00Z',
                    'inicio_vuelta' => null,
                    'fin' => null,
                    'estado' => 'explorando',
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
                ],
            ],
            'terminadas' => [],
        ]);

        // huida
        $view->assertSee('huye antes de que comience el combate', false);
        $view->assertSee('Rattata', false);
        $view->assertSee('/images/iconos_webp/19.webp', false);
        // emboscada: título + subtítulo según resolución + mini-iconos
        $view->assertSee('¡Emboscada!', false);
        $view->assertSee('El equipo repele el ataque (-10 min)', false);
        $view->assertSee('El equipo escapa perdiendo tiempo', false);
        $view->assertSee('/images/iconos_webp/129.webp', false);
        // contratiempo (desorientación)
        $view->assertSee('El equipo pierde el rastro. -15 min', false);
        // retirada
        $view->assertSee('El equipo se retira:', false);
        $view->assertSee('demasiadas bajas', false);
        // grupo + mini-iconos
        $view->assertSee('El equipo se encuentra con un grupo salvaje', false);
        $view->assertSee('/images/iconos_webp/25.webp', false);
        $view->assertSee('/images/iconos_webp/26.webp', false);
        // hallazgo familia
        $view->assertSee('Caramelos de', false);
        $view->assertSee('/images/candy_pokemon/19.webp', false);
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
        // encuentro grupo + derrota
        $view->assertSee('Grupo salvaje', false);
        $view->assertSee('· Derrota', false);
        $view->assertSee('/images/iconos_webp/25.webp', false);
        // neutral → texto + SVG informativo (sin imagen de pokémon)
        $view->assertSee('Evento neutral', false);
        $view->assertDontSee('Derrotados');
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
                [
                    'id' => 50,
                    'equipo' => 'Equipo B',
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
                ],
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
                [
                    'id' => 60,
                    'equipo' => 'Equipo A',
                    'habitat' => 'Bosque',
                    'habitat_id' => 1,
                    'nivel' => 1,
                    'indefinido' => false,
                    'duracion_horas' => 4,
                    'inicio' => '2026-08-30T10:00:00Z',
                    'inicio_vuelta' => null,
                    'fin' => null,
                    'estado' => 'explorando',
                    'progreso' => 10,
                    'bitacora' => [
                        ['tipo' => 'pokemon', 'pokemon_id' => 19, 'nombre' => 'Rattata', 'timestamp' => '2026-08-30T10:05:00Z'],
                    ],
                ],
            ],
            'terminadas' => [
                [
                    'id' => 61,
                    'equipo' => 'Equipo B',
                    'habitat' => 'Bosque',
                    'nivel' => 1,
                    'resultado' => [
                        'capturados' => [],
                        'caramelos_familia' => [],
                        'caramelos_ev' => [],
                        'caramelos_tipo' => [],
                        'exp' => 0,
                    ],
                ],
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
}
