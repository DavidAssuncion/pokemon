<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Battle\Domain\FabricaBatallaInterface;
use Src\Battle\Infrastructure\FabricaBatallaMock;
use Src\Equipos\Domain\TeamRepositoryInterface;
use Src\Equipos\Infra\EloquentTeamRepository;
use Src\Habitats\Domain\Repositories\HabitatRepositoryInterface;
use Src\Habitats\Infra\HabitatRepository;
use Src\Reclutamiento\Domain\ReclutamientoRepositoryInterface;
use Src\Reclutamiento\Infra\EloquentReclutamientoRepository;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
