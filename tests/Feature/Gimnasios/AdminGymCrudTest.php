<?php

declare(strict_types=1);

namespace Tests\Feature\Gimnasios;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CRUD admin de gyms: crear, editar datos fijos y editar etapas. NO existe
 * borrado ni desactivación (requisito 1.4).
 */
class AdminGymCrudTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_crea_gym_con_datos_y_etapas(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/api/admin/gyms', [
            'slug' => 'test-gym',
            'medalla' => 'Medalla Test',
            'tipo' => 7,
            'nivel_minimo' => 10,
            'etapas' => [
                1 => ['vanguardia' => [1, 2], 'retaguardia' => [3]],
                2 => ['vanguardia' => [], 'retaguardia' => [4]],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('slug', 'test-gym');
        $response->assertJsonPath('medalla', 'Medalla Test');
        $response->assertJsonPath('tipo', 7);
        $response->assertJsonPath('nivel_minimo', 10);
        $response->assertJsonPath('etapas.1.vanguardia', [1, 2]);
        $response->assertJsonPath('etapas.1.retaguardia', [3]);
        $response->assertJsonPath('etapas.2.vanguardia', []);
        $response->assertJsonPath('etapas.2.retaguardia', [4]);
    }

    #[Test]
    public function test_edita_datos_fijos_del_gym(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson('/api/admin/gyms', [
            'slug' => 'test-gym',
            'medalla' => 'Medalla Test',
            'tipo' => 7,
            'nivel_minimo' => 10,
        ])->assertCreated();

        $response = $this->putJson('/api/admin/gyms/test-gym', [
            'medalla' => 'Medalla Nueva',
            'tipo' => 8,
            'nivel_minimo' => 25,
        ]);

        $response->assertOk();
        $response->assertJsonPath('medalla', 'Medalla Nueva');
        $response->assertJsonPath('tipo', 8);
        $response->assertJsonPath('nivel_minimo', 25);
    }

    #[Test]
    public function test_edita_etapa_del_gym(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson('/api/admin/gyms', [
            'slug' => 'test-gym',
            'medalla' => 'Medalla Test',
            'tipo' => 7,
            'nivel_minimo' => 10,
            'etapas' => [
                1 => ['vanguardia' => [1], 'retaguardia' => [2]],
            ],
        ])->assertCreated();

        $response = $this->putJson('/api/admin/gyms/test-gym/stages/1', [
            'vanguardia' => [10, 11],
            'retaguardia' => [12],
        ]);

        $response->assertOk();
        $response->assertJsonPath('etapa', 1);
        $response->assertJsonPath('vanguardia', [10, 11]);
        $response->assertJsonPath('retaguardia', [12]);
    }

    #[Test]
    public function test_obtener_detalle_admin_incluye_etapas(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson('/api/admin/gyms', [
            'slug' => 'test-gym',
            'medalla' => 'Medalla Test',
            'tipo' => 7,
            'nivel_minimo' => 10,
            'etapas' => [
                3 => ['vanguardia' => [5], 'retaguardia' => [6]],
            ],
        ])->assertCreated();

        $response = $this->getJson('/api/admin/gyms/test-gym');

        $response->assertOk();
        $response->assertJsonPath('slug', 'test-gym');
        $response->assertJsonPath('etapas.3.vanguardia', [5]);
        $response->assertJsonPath('etapas.3.retaguardia', [6]);
    }

    #[Test]
    public function test_no_existe_ruta_de_borrado(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson('/api/admin/gyms', [
            'slug' => 'test-gym',
            'medalla' => 'Medalla Test',
            'tipo' => 7,
            'nivel_minimo' => 10,
        ])->assertCreated();

        // No existe método de borrado: ni 404 (ruta inexistente) ni 200.
        $response = $this->deleteJson('/api/admin/gyms/test-gym');
        $this->assertTrue(in_array($response->status(), [404, 405], true));
    }

    #[Test]
    public function test_gym_con_etapa_vacia_o_species_inexistentes_no_rompe(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/api/admin/gyms', [
            'slug' => 'test-gym',
            'medalla' => 'Medalla Test',
            'tipo' => 7,
            'nivel_minimo' => 10,
            'etapas' => [
                1 => ['vanguardia' => [999999], 'retaguardia' => []],
                2 => ['vanguardia' => [], 'retaguardia' => []],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('etapas.1.vanguardia', [999999]);
        $response->assertJsonPath('etapas.1.retaguardia', []);
        $response->assertJsonPath('etapas.2.vanguardia', []);
        $response->assertJsonPath('etapas.2.retaguardia', []);
    }
}
