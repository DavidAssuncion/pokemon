<?php

declare(strict_types=1);

namespace Tests\Feature\Gimnasios;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Los gyms se registran en el Datagrid usando el slug como clave de
 * referencia (requisito 1.5).
 */
class GymDatagridTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_gym_listable_en_datagrid_con_slug_como_clave(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson('/api/admin/gyms', [
            'slug' => 'datagrid-gym',
            'medalla' => 'Medalla Datagrid',
            'tipo' => 8,
            'nivel_minimo' => 10,
            'etapas' => [
                1 => ['vanguardia' => [1], 'retaguardia' => [2]],
            ],
        ])->assertCreated();

        $response = $this->getJson('/datagrid/gym?filter[slug]=datagrid-gym');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('datagrid-gym', $data[0]['slug']);
        $this->assertSame('Medalla Datagrid', $data[0]['medalla']);
    }

    #[Test]
    public function test_gym_detalle_datagrid_expone_slug_medalla_y_etapas(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson('/api/admin/gyms', [
            'slug' => 'datagrid-gym',
            'medalla' => 'Medalla Datagrid',
            'tipo' => 8,
            'nivel_minimo' => 10,
            'etapas' => [
                2 => ['vanguardia' => [5], 'retaguardia' => [6]],
            ],
        ])->assertCreated();

        $gymId = \App\Models\Gym::query()->where('slug', 'datagrid-gym')->value('id');

        $response = $this->getJson("/datagrid/gym/{$gymId}/detalle");

        $response->assertOk();
        $this->assertSame('datagrid-gym', $response->json('slug'));
        $this->assertSame(8, $response->json('tipo'));
        $this->assertSame([5], $response->json('etapas.2.vanguardia'));
        $this->assertSame([6], $response->json('etapas.2.retaguardia'));
    }
}
