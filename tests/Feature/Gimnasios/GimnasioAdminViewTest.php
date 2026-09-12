<?php

declare(strict_types=1);

namespace Tests\Feature\Gimnasios;

use Tests\TestCase;

/**
 * Pruebas de vista (Blade) de la nueva página de Gestión-Admin de gimnasios
 * (resources/views/gimnasios/admin.blade.php): popup de listado de todos los
 * gimnasios, popup de detalle con 4 pestañas (etapas), listado de pokémon por
 * tipo filtrado a SOLO última evolución, y edición vía PATCH.
 */
class GimnasioAdminViewTest extends TestCase
{
    public function test_admin_view_renders_gestión_modal_button(): void
    {
        $view = $this->view('gimnasios.admin');
        $view->assertSee('GESTION-ADMIN', false);
        $view->assertSee('openGestionModal()', false);
        $view->assertSee('gimnasioAdminApp', false);
    }

    public function test_admin_view_has_list_modal_with_all_gyms_fetched_from_endpoint(): void
    {
        $view = $this->view('gimnasios.admin');

        // Capa 1: popup de listado de TODOS los gyms.
        $view->assertSee('Todos los gimnasios', false);
        $view->assertSee('/api/admin/gyms', false);
        $view->assertSee('showGestionModal', false);
        $view->assertSee('loadGyms()', false);
        $view->assertSee('openGymDetail(gym)', false);
    }

    public function test_admin_view_has_detail_modal_with_four_tabs(): void
    {
        $view = $this->view('gimnasios.admin');

        // Capa 2: detalle del gym con 4 pestañas de entrenador (etapas 1-4).
        $view->assertSee('showDetailModal', false);
        $view->assertSee('[1,2,3,4]', false);
        $view->assertSee('role="tablist"', false);
        $view->assertSee("'Etapa ' + etapa", false);
        $view->assertSee('/api/admin/gyms/', false);
    }

    public function test_admin_view_edit_fields_are_present(): void
    {
        $view = $this->view('gimnasios.admin');

        $view->assertSee('edit-medalla', false);
        $view->assertSee('edit-tipo', false);
        $view->assertSee('edit-nivel-minimo', false);
        $view->assertSee('form.etapas[etapa].vanguardia', false);
        $view->assertSee('form.etapas[etapa].retaguardia', false);
        $view->assertSee("method: 'PUT'", false);
        $view->assertSee('guardarGym()', false);
        $view->assertSee('Guardar cambios', false);
    }

    public function test_admin_view_shows_type_filtered_last_evolution_pokemon(): void
    {
        $view = $this->view('gimnasios.admin');

        // A.3/A.4: filtro por tipo aplicado por defecto + solo última evolución.
        $view->assertSee('pokemonFiltered', false);
        $view->assertSee('es_ultima_evolucion', false);
        $view->assertSee('evolves_from_species_id', false);
        $view->assertSee('/datagrid/pokemon', false);
    }

    public function test_admin_route_is_registered_before_slug_route(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutesByName());
        $this->assertArrayHasKey('gimnasios.admin', $routes->all());
        $this->assertArrayHasKey('gimnasios.show', $routes->all());

        // admin debe quedar registrado antes que {slug} para no ser absorbido por él.
        $adminPos = array_search('gimnasios.admin', array_keys($routes->all()), true);
        $showPos = array_search('gimnasios.show', array_keys($routes->all()), true);
        $this->assertLessThan($showPos, $adminPos);
    }
}
