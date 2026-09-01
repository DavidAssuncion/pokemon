<?php

declare(strict_types=1);

namespace Tests\Feature\Gimnasios;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Src\Gimnasios\App\RegistrarResultadoGimnasio;
use Tests\TestCase;

class GimnasioCombateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private RegistrarResultadoGimnasio $registrar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->registrar = $this->app->make(RegistrarResultadoGimnasio::class);
    }

    #[Test]
    public function test_progresion_secuencial_avanza_etapa_por_etapa(): void
    {
        $userId = (int) $this->user->id;
        $gymId = 'bug';

        // Sin progreso → etapa 1
        $this->assertNull($this->obtenerProgreso($userId, $gymId));

        // Gana etapa 1 → etapa 2
        $this->registrar->registrar($gymId, 1, $userId, true, $userId);
        $this->assertSame(2, $this->obtenerProgreso($userId, $gymId));

        // Gana etapa 2 → etapa 3
        $this->registrar->registrar($gymId, 2, $userId, true, $userId);
        $this->assertSame(3, $this->obtenerProgreso($userId, $gymId));

        // Gana etapa 3 → etapa 4
        $this->registrar->registrar($gymId, 3, $userId, true, $userId);
        $this->assertSame(4, $this->obtenerProgreso($userId, $gymId));

        // Gana etapa 4 (líder) → completado (etapa 5)
        $this->registrar->registrar($gymId, 4, $userId, true, $userId);
        $this->assertSame(5, $this->obtenerProgreso($userId, $gymId));
        $this->assertTrue($this->esCompletado($userId, $gymId));
    }

    #[Test]
    public function test_no_avanza_si_pierde(): void
    {
        $userId = (int) $this->user->id;
        $gymId = 'bug';

        // Pierde etapa 1 → sigue etapa 1
        $this->registrar->registrar($gymId, 1, $userId, false, $userId);
        $this->assertNull($this->obtenerProgreso($userId, $gymId));
    }

    #[Test]
    public function test_no_retrocede_ni_se_repite_tras_completar(): void
    {
        $userId = (int) $this->user->id;
        $gymId = 'bug';

        // Simula completar todas las etapas
        $this->registrar->registrar($gymId, 1, $userId, true, $userId);
        $this->registrar->registrar($gymId, 2, $userId, true, $userId);
        $this->registrar->registrar($gymId, 3, $userId, true, $userId);
        $this->registrar->registrar($gymId, 4, $userId, true, $userId);

        $this->assertSame(5, $this->obtenerProgreso($userId, $gymId));

        // registrar de nuevo etapa 4 → no cambia (ya completed)
        $this->registrar->registrar($gymId, 4, $userId, true, $userId);
        $this->assertSame(5, $this->obtenerProgreso($userId, $gymId));
    }

    #[Test]
    public function test_idor_no_avanza_si_user_id_no_coincide(): void
    {
        $userId = (int) $this->user->id;
        $otherUserId = $userId + 999;
        $gymId = 'bug';

        // won=true, pero userId != authUserId → no avanza
        $this->registrar->registrar($gymId, 1, $otherUserId, true, $userId);
        $this->assertNull($this->obtenerProgreso($otherUserId, $gymId));
    }

    #[Test]
    public function test_recompensas_solo_con_won_true(): void
    {
        $userId = (int) $this->user->id;
        $gymId = 'bug';

        // won=false → no avance
        $resultado = $this->registrar->registrar($gymId, 1, $userId, false, $userId);
        $this->assertFalse($resultado['avance']);
        $this->assertFalse($resultado['completado']);
        $this->assertNull($resultado['medalla']);

        // won=true → avance
        $resultado = $this->registrar->registrar($gymId, 1, $userId, true, $userId);
        $this->assertTrue($resultado['avance']);
        $this->assertFalse($resultado['completado']);
        $this->assertNull($resultado['medalla']);
    }

    #[Test]
    public function test_medalla_al_derrotar_lider(): void
    {
        $userId = (int) $this->user->id;
        $gymId = 'bug';

        // Avanza hasta etapa 4 (líder)
        $this->registrar->registrar($gymId, 1, $userId, true, $userId);
        $this->registrar->registrar($gymId, 2, $userId, true, $userId);
        $this->registrar->registrar($gymId, 3, $userId, true, $userId);

        // Gana al líder → completado, medalla
        $resultado = $this->registrar->registrar($gymId, 4, $userId, true, $userId, 'Medalla Bicho');
        $this->assertTrue($resultado['avance']);
        $this->assertTrue($resultado['completado']);
        $this->assertSame('Medalla Bicho', $resultado['medalla']);
    }

    private function obtenerProgreso(int $userId, string $gymId): ?int
    {
        return $this->app->make(\Src\Gimnasios\Domain\Repositories\GymProgressRepositoryInterface::class)
            ->obtenerProgreso($userId, $gymId);
    }

    private function esCompletado(int $userId, string $gymId): bool
    {
        return $this->app->make(\Src\Gimnasios\Domain\Repositories\GymProgressRepositoryInterface::class)
            ->esCompletado($userId, $gymId);
    }
}
