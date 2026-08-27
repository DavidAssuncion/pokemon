<?php

declare(strict_types=1);

namespace App\Providers;

use App\Bus\DatabaseUnitOfWork;
use App\Bus\LaravelCommandBus;
use App\Models\User;
use App\Support\WebpConverter;
use App\Support\WebpConverterInterface;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Src\Battle\Domain\FabricaBatallaInterface;
use Src\Battle\Infrastructure\FabricaBatallaMock;
use Src\Equipos\Domain\TeamRepositoryInterface;
use Src\Equipos\Infra\EloquentTeamRepository;
use Src\Habitats\Domain\Repositories\HabitatRepositoryInterface;
use Src\Habitats\Infra\HabitatRepository;
use Src\Reclutamiento\Domain\ReclutamientoRepositoryInterface;
use Src\Reclutamiento\Infra\EloquentReclutamientoRepository;
use Src\Shared\Bus\CommandBus;
use Src\Shared\Bus\UnitOfWork;
use Src\Shared\Domain\NivelHelper;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TeamRepositoryInterface::class, EloquentTeamRepository::class);
        $this->app->bind(ReclutamientoRepositoryInterface::class, EloquentReclutamientoRepository::class);
        $this->app->bind(FabricaBatallaInterface::class, FabricaBatallaMock::class);
        $this->app->bind(HabitatRepositoryInterface::class, HabitatRepository::class);
        $this->app->bind(WebpConverterInterface::class, WebpConverter::class);
        $this->app->bind(CommandBus::class, LaravelCommandBus::class);
        $this->app->bind(UnitOfWork::class, DatabaseUnitOfWork::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Single-player: compartir nivel y progreso del jugador con todas las vistas (header del layout).
        $user = User::first();
        // getAttribute() evita el cast del modelo: en BDs sin la columna migrada el valor
        // llega null, y (int) lo normaliza a 0 (mismo comportamiento que sin usuario).
        $experiencia = $user !== null ? (int) $user->getAttribute('experiencia') : 0;

        View::share('nivelJugador', NivelHelper::nivelDesdeExperiencia($experiencia));
        View::share('progresoNivel', NivelHelper::progresoHaciaSiguienteNivel($experiencia));
    }
}
