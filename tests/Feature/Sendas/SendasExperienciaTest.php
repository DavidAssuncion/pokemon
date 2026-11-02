<?php

declare(strict_types=1);

namespace Tests\Feature\Sendas;

use App\Enums\TipoEnum;
use App\Models\PlayerInventory;
use App\Models\PlayerTypePath;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SendasExperienciaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    #[Test]
    public function test_consumir_caramelos_hasta_el_tope_actualiza_el_nivel(): void
    {
        $this->crearProgreso(71_000, 4);
        $this->crearCaramelos(50);

        $this->postJson('/sendas/experiencia', [
            'tipo' => 'fuego',
            'cantidad' => 10,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('cantidad_consumida', 10)
            ->assertJsonPath('exp_ganada', 1_000)
            ->assertJsonPath('caramelos', 40);

        $this->assertDatabaseHas('player_type_paths', [
            'user_id' => $this->user->id,
            'type_id' => TipoEnum::FIRE->value,
            'exp' => 72_000,
            'level' => 5,
        ]);
    }

    #[Test]
    public function test_rechaza_el_consumo_si_supera_el_tope_sin_gastar_caramelos(): void
    {
        $this->crearProgreso(71_950, 4);
        $this->crearCaramelos(50);

        $response = $this->postJson('/sendas/experiencia', [
            'tipo' => 'fuego',
            'cantidad' => 1,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('72000', (string) $response->json('error'));
        $this->assertStringContainsString('50', (string) $response->json('error'));

        $this->assertDatabaseHas('player_type_paths', [
            'user_id' => $this->user->id,
            'type_id' => TipoEnum::FIRE->value,
            'exp' => 71_950,
        ]);
        $this->assertDatabaseHas('player_inventory', [
            'user_id' => $this->user->id,
            'item_key' => 'tipo:fuego',
            'cantidad' => 50,
        ]);
    }

    #[Test]
    public function test_rechaza_el_consumo_cuando_ya_esta_en_el_tope(): void
    {
        $this->crearProgreso(72_000, 5);
        $this->crearCaramelos(50);

        $response = $this->postJson('/sendas/experiencia', [
            'tipo' => 'fuego',
            'cantidad' => 1,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('72000', (string) $response->json('error'));

        $this->assertDatabaseHas('player_inventory', [
            'user_id' => $this->user->id,
            'item_key' => 'tipo:fuego',
            'cantidad' => 50,
        ]);
    }

    #[Test]
    public function test_el_tope_de_experiencia_es_ajustable_desde_config(): void
    {
        config(['sendas.experiencia.maxima' => 100_000]);
        $this->crearProgreso(72_000, 5);
        $this->crearCaramelos(10);

        $this->postJson('/sendas/experiencia', [
            'tipo' => 'fuego',
            'cantidad' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('cantidad_consumida', 1);

        $this->assertDatabaseHas('player_type_paths', [
            'user_id' => $this->user->id,
            'type_id' => TipoEnum::FIRE->value,
            'exp' => 72_100,
            'level' => 5,
        ]);
    }

    #[Test]
    public function test_consume_parcialmente_los_caramelos_disponibles_hasta_el_tope(): void
    {
        $this->crearProgreso(71_000, 4);
        $this->crearCaramelos(10);

        $this->postJson('/sendas/experiencia', [
            'tipo' => 'fuego',
            'cantidad' => 100,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('cantidad_consumida', 10)
            ->assertJsonPath('exp_ganada', 1_000)
            ->assertJsonPath('caramelos', 0);

        $this->assertDatabaseHas('player_type_paths', [
            'user_id' => $this->user->id,
            'type_id' => TipoEnum::FIRE->value,
            'exp' => 72_000,
            'level' => 5,
        ]);
    }

    #[Test]
    public function test_en_el_tope_no_consume_ningun_caramelo(): void
    {
        $this->crearProgreso(72_000, 5);
        $this->crearCaramelos(10);

        $response = $this->postJson('/sendas/experiencia', [
            'tipo' => 'fuego',
            'cantidad' => 10,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('72000', (string) $response->json('error'));

        $this->assertDatabaseHas('player_type_paths', [
            'user_id' => $this->user->id,
            'type_id' => TipoEnum::FIRE->value,
            'exp' => 72_000,
        ]);
        $this->assertDatabaseHas('player_inventory', [
            'user_id' => $this->user->id,
            'item_key' => 'tipo:fuego',
            'cantidad' => 10,
        ]);
    }

    private function crearProgreso(int $exp, int $nivel): void
    {
        PlayerTypePath::query()->create([
            'user_id' => $this->user->id,
            'type_id' => TipoEnum::FIRE->value,
            'exp' => $exp,
            'level' => $nivel,
        ]);
    }

    private function crearCaramelos(int $cantidad): void
    {
        PlayerInventory::query()->create([
            'user_id' => $this->user->id,
            'item_key' => 'tipo:fuego',
            'cantidad' => $cantidad,
        ]);
    }
}
