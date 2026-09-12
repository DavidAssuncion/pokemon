<?php

declare(strict_types=1);

namespace Tests\Feature\Gimnasios;

use App\Models\Gym;
use App\Models\GymStage;
use Database\Seeders\GymSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Src\Gimnasios\Domain\Repositories\GymCatalogoRepositoryInterface;
use Tests\TestCase;

class GymSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_seeder_inserta_los_18_gyms_con_sus_etapas(): void
    {
        $this->seed(GymSeeder::class);

        $this->assertSame(18, Gym::query()->count());

        $repo = $this->app->make(GymCatalogoRepositoryInterface::class);

        // El gimnasio bug conserva las especies de su etapa 1 del catálogo
        $bug = $repo->obtenerPorSlugOrFail('bug');
        $this->assertSame('Medalla Bicho', $bug->medalla);
        $this->assertSame([268, 266], $bug->equipos[1]->vanguardia->all());
        $this->assertSame([900], $bug->equipos[1]->retaguardia->all());

        // dragon es el de mayor nivel mínimo
        $dragon = $repo->obtenerPorSlugOrFail('dragon');
        $this->assertSame(100, $dragon->nivelMinimo);

        // Cada gimnasio tiene sus etapas persistidas en gym_stages
        $this->assertSame(18 * 4, GymStage::query()->count());
        $this->assertGreaterThanOrEqual(1, $bug->equipos[1]->vanguardia->count());
    }

    #[Test]
    public function test_seeder_es_idempotente(): void
    {
        $this->seed(GymSeeder::class);
        $this->seed(GymSeeder::class);

        $this->assertSame(18, Gym::query()->count());
        $this->assertSame(18 * 4, GymStage::query()->count());
    }
}
