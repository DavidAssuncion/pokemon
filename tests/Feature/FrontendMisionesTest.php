<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class FrontendMisionesTest extends TestCase
{
    /**
     * La vista de Misiones renderiza el componente Alpine con el listado de
     * misiones disponibles, la misión activa y los flujos aceptar/cancelar/pelear.
     */
    public function test_misiones_view_renders_listing_active_and_actions(): void
    {
        $view = $this->view('misiones.index', [
            'misionPeleaRuta' => null,
        ]);

        // Componente Alpine y cabecera
        $view->assertSee('x-data="misionesPage()"', false);
        $view->assertSee('Misiones', false);
        $view->assertSee('Acepta misiones y cumple los objetivos', false);

        // Fuentes de datos (tolerantes al backend en preparación)
        $view->assertSee("'/misiones'", false);
        $view->assertSee("'/misiones/activa'", false);
        $view->assertSee("'/api/reclutados'", false);
        $view->assertSee("'/datagrid/habitat?per_page=200'", false);

        // Listado: card por misión con objetivo y recompensa
        $view->assertSee('Disponibles', false);
        $view->assertSee('objetivoTexto(mision)', false);
        $view->assertSee('recompensasLista(mision)', false);
        $view->assertSee('Aceptar', false);

        // Modal de aceptar: select de reclutado y de zona (contrato exige habitat_id)
        $view->assertSee('abrirAceptar(mision)', false);
        $view->assertSee("'/misiones/' + this.acceptMissionId + '/aceptar'", false);
        $view->assertSee('reclutado_id: this.acceptReclutadoId', false);
        $view->assertSee('habitat_id: this.acceptHabitatId', false);

        // Misión activa: progreso, cancelar y pelea (placeholder)
        $view->assertSee('Misión activa', false);
        $view->assertSee('progresoPorcentaje(activa)', false);
        $view->assertSee("'/misiones/' + this.activa.id + '/cancelar'", false);
        $view->assertSee('Cancelar misión', false);
        $view->assertSee('¡Pelea!', false);
        $view->assertSee('La pelea de misiones está en preparación.', false);
    }

    /**
     * Con una ruta de pelea expuesta por el backend, el botón ¡Pelea! navega a ella.
     */
    public function test_misiones_view_uses_battle_route_when_exposed(): void
    {
        $view = $this->view('misiones.index', [
            'misionPeleaRuta' => '/misiones/combate/:id',
        ]);

        $view->assertSee('rutaPelea.replace(\':id\'', false);
    }
}
