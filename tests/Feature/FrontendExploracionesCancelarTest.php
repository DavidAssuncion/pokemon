<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class FrontendExploracionesCancelarTest extends TestCase
{
    /**
     * Cada tarjeta de exploración ACTIVA muestra el botón secundario "Cancelar"
     * y dispara POST a /exploraciones/{id}/cancelar con confirmación; el botón
     * "Recoger resultados" solo sigue apareciendo para indefinidas.
     */
    public function test_exploraciones_activas_render_cancel_button(): void
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
                    'bitacora' => [],
                ],
                [
                    'id' => 35,
                    'equipo' => 'Equipo C',
                    'habitat' => 'Lago Místico',
                    'habitat_id' => 7,
                    'nivel' => 3,
                    'indefinido' => true,
                    'duracion_horas' => null,
                    'inicio' => '2026-08-27T10:00:00Z',
                    'inicio_vuelta' => null,
                    'fin' => null,
                    'estado' => 'explorando',
                    'progreso' => 10,
                    'bitacora' => [],
                ],
            ],
            'terminadas' => [],
        ]);

        // Botón Cancelar presente (uno por tarjeta activa)
        $view->assertSee('Cancelar', false);
        $view->assertSee('cancelarExploracion(', false);
        $view->assertSee('/exploraciones/${id}/cancelar', false);
        // Mensaje de confirmación antes de cancelar
        $view->assertSee('¿Cancelar la exploración? No recibirás recompensas.', false);
        // Recoger resultados sigue solo para indefinidas
        $view->assertSee('recogerResultados(', false);
        $view->assertSee('/exploraciones/${id}/recoger', false);
    }
}
