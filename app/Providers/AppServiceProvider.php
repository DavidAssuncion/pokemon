<?php

declare(strict_types=1);

namespace App\Providers;

use Src\CombateEntrenadores\Domain\Repositories\EntrenadorLogRepositoryInterface;
use Src\CombateEntrenadores\Infra\EloquentEntrenadorLogRepository;
use App\Bus\DatabaseUnitOfWork;
use App\Bus\LaravelCommandBus;
use App\Support\WebpConverter;
use App\Support\WebpConverterInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Src\Battle\Domain\FabricaBatallaInterface;
use Src\Battle\Infrastructure\FabricaBatallaMock;
use Src\Equipos\Domain\TeamRepositoryInterface;
use Src\Equipos\Infra\EloquentTeamRepository;
use Src\Habitats\Domain\Repositories\HabitatRepositoryInterface;
use Src\Habitats\Infra\HabitatRepository;
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
        $this->app->bind(FabricaBatallaInterface::class, FabricaBatallaMock::class);
        $this->app->bind(HabitatRepositoryInterface::class, HabitatRepository::class);
        $this->app->bind(WebpConverterInterface::class, WebpConverter::class);
        $this->app->bind(CommandBus::class, LaravelCommandBus::class);
        $this->app->bind(UnitOfWork::class, DatabaseUnitOfWork::class);
        $this->app->bind(EntrenadorLogRepositoryInterface::class, EloquentEntrenadorLogRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Multiplayer: compartir nivel y progreso del jugador AUTENTICADO con todas las
        // vistas (header del layout). El usuario se resuelve al renderizar (composer),
        // no en boot: durante el boot de providers la sesión aún no ha arrancado y
        // auth()->user() devolvería null. En CLI sin auth → experiencia 0 / nivel 1.
        // Se verifica la existencia de la tabla en boot para no romper el arranque en
        // una base recién migrada (p. ej. `artisan migrate` sobre una BD vacía).
        $usersTableExists = Schema::hasTable('users');

        View::composer('*', function (\Illuminate\View\View $view) use ($usersTableExists): void {
            // Los datos explícitos de la vista (p. ej. tests) ganan al share por defecto.
            if ($view->offsetExists('nivelJugador') && $view->offsetExists('progresoNivel')) {
                return;
            }

            $user = $usersTableExists ? Auth::user() : null;
            // getAttribute() evita el cast del modelo: en BDs sin la columna migrada el valor
            // llega null, y (int) lo normaliza a 0 (mismo comportamiento que sin usuario).
            $experiencia = $user !== null ? (int) $user->getAttribute('experiencia') : 0;

            $view->with('nivelJugador', NivelHelper::nivelDesdeExperiencia($experiencia));
            $view->with('progresoNivel', NivelHelper::progresoHaciaSiguienteNivel($experiencia));
        });
    }
}
