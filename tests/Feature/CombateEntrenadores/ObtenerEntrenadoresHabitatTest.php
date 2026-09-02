<?php

declare(strict_types=1);

namespace Tests\Feature\CombateEntrenadores;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ObtenerEntrenadoresHabitatTest extends TestCase
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
    public function test_no_devuelve_pokemon_en_la_respuesta(): void
    {
        $response = $this->getJson('/api/habitats/999/entrenadores');

        // Aunque el hábitat no exista, la estructura de la respuesta
        // debe ser {nivel: [{indice, desbloqueado}]} sin pokemon
        $response->assertOk();

        $data = $response->json();
        $this->assertIsArray($data);

        foreach ($data as $nivel => $entrenadores) {
            $this->assertIsArray($entrenadores);
            foreach ($entrenadores as $entrenador) {
                $this->assertArrayHasKey('indice', $entrenador);
                $this->assertArrayHasKey('desbloqueado', $entrenador);
                $this->assertArrayNotHasKey('pokemon', $entrenador);
            }
        }
    }

    #[Test]
    public function test_devuelve_solo_indice_y_desbloqueado(): void
    {
        $response = $this->getJson('/api/habitats/999/entrenadores');

        $response->assertOk();
        $data = $response->json();

        // 3 niveles, cada uno con 3 entrenadores
        $this->assertCount(3, $data);

        foreach ($data as $nivel => $entrenadores) {
            $this->assertCount(3, $entrenadores);
            foreach ($entrenadores as $entrenador) {
                $this->assertSame(['indice', 'desbloqueado'], array_keys($entrenador));
            }
        }
    }
}
