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
                            ['stat' => 2, 'stat_nombre' => 'Ataque', 'cantidad' => 8],
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
        $view->assertSee('Avistados', false);
        $view->assertSee('Capturados', false);
        $view->assertSee('×3', false);
        $view->assertSee('Caramelos de familia', false);
        $view->assertSee('×12', false);
        $view->assertSee('Caramelos EV', false);
        $view->assertSee('Ataque', false);
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
        $view->assertSee('Evento registrado', false);
        // Bloques vacíos omitidos y EXP 0 visible
        $view->assertSee('+0 EXP', false);
        $view->assertSee('Sin resultados', false);
    }
}
