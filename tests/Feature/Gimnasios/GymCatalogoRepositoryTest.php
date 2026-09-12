<?php

declare(strict_types=1);

namespace Tests\Feature\Gimnasios;

use App\Models\Gym;
use App\Models\GymStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Src\Gimnasios\Domain\DataTransferObjects\EquipoEtapaGimnasio;
use Src\Gimnasios\Domain\Exceptions\GimnasioNoExiste;
use Src\Gimnasios\Domain\Repositories\GymCatalogoRepositoryInterface;
use Src\Shared\Collections\IntCollection;
use Src\Shared\Tipos\TipoPokemon;
use Tests\TestCase;

class GymCatalogoRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_insertar_guarda_gym_y_etapas_y_los_devuelve_como_entidad(): void
    {
        $repo = $this->app->make(GymCatalogoRepositoryInterface::class);

        $gym = $repo->insertar(
            slug: 'test',
            medalla: 'Medalla Test',
            tipo: TipoPokemon::BICHO,
            nivelMinimo: 10,
            equipos: [
                1 => new EquipoEtapaGimnasio(new IntCollection([1, 2]), new IntCollection([3])),
                2 => new EquipoEtapaGimnasio(new IntCollection([4]), new IntCollection([5])),
            ],
        );

        $this->assertSame('test', $gym->slug);
        $this->assertSame('Medalla Test', $gym->medalla);
        $this->assertSame(TipoPokemon::BICHO, $gym->tipo);
        $this->assertSame(10, $gym->nivelMinimo);

        // Etapas vuelven como EquipoEtapaGimnasio con IntCollection de species_id
        $this->assertCount(2, $gym->equipos);
        $this->assertSame([1, 2], $gym->equipos[1]->vanguardia->all());
        $this->assertSame([3], $gym->equipos[1]->retaguardia->all());

        $this->assertSame(1, Gym::query()->count());
        $this->assertSame(2, GymStage::query()->count());
    }

    #[Test]
    public function test_obtenerTodos_y_obtenerPorSlugOrFail(): void
    {
        $repo = $this->app->make(GymCatalogoRepositoryInterface::class);
        $repo->insertar('a', 'Medalla A', TipoPokemon::BICHO, 10);
        $repo->insertar('b', 'Medalla B', TipoPokemon::FUEGO, 20);

        $todos = $repo->obtenerTodos();
        $this->assertCount(2, $todos);

        $gym = $repo->obtenerPorSlugOrFail('b');
        $this->assertSame('Medalla B', $gym->medalla);
        $this->assertSame(TipoPokemon::FUEGO, $gym->tipo);
        $this->assertSame(20, $gym->nivelMinimo);
    }

    #[Test]
    public function test_obtenerPorSlugOrFail_lanza_GimnasioNoExiste(): void
    {
        $repo = $this->app->make(GymCatalogoRepositoryInterface::class);

        $this->expectException(GimnasioNoExiste::class);
        $repo->obtenerPorSlugOrFail('no-existe');
    }

    #[Test]
    public function test_actualizarDatos_y_actualizarEtapas(): void
    {
        $repo = $this->app->make(GymCatalogoRepositoryInterface::class);
        $repo->insertar('a', 'Medalla A', TipoPokemon::BICHO, 10);

        $actualizado = $repo->actualizarDatos('a', 'Medalla A2', TipoPokemon::LUCHA, 99);
        $this->assertSame('Medalla A2', $actualizado->medalla);
        $this->assertSame(TipoPokemon::LUCHA, $actualizado->tipo);
        $this->assertSame(99, $actualizado->nivelMinimo);

        $repo->actualizarEtapas('a', [
            1 => new EquipoEtapaGimnasio(new IntCollection([10]), new IntCollection([20])),
        ]);

        $gym = $repo->obtenerPorSlugOrFail('a');
        $this->assertSame([10], $gym->equipos[1]->vanguardia->all());
        $this->assertSame([20], $gym->equipos[1]->retaguardia->all());
    }

    #[Test]
    public function test_actualizarDatos_lanza_GimnasioNoExiste_si_el_slug_no_existe(): void
    {
        $repo = $this->app->make(GymCatalogoRepositoryInterface::class);

        $this->expectException(GimnasioNoExiste::class);
        $repo->actualizarDatos('no-existe', 'X', TipoPokemon::BICHO, 1);
    }
}
