<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GimnasiosViewController;
use App\Http\Controllers\HabitatsController;
use App\Http\Controllers\IconoController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('auth')->group(function (): void {
    Route::get('/', [HabitatsController::class, 'index']);
    Route::get('/habitats', [HabitatsController::class, 'index']);

    Route::get('/iconos/{filename}', [IconoController::class, 'show']);
    Route::get('/iconos/shiny/{filename}', [IconoController::class, 'shiny']);

    Route::get('/cruds', [DashboardController::class, 'cruds'])->name('cruds');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Combate — ver src/Battle/
    Route::get('/combate', function () {
        return view('combate_page');
    });

    // Gimnasios — vistas (la API vive en routes/gimnasios.php, sin duplicar)
    Route::get('/gimnasios', [GimnasiosViewController::class, 'index'])->name('gimnasios.index');
    Route::get('/gimnasios/{slug}', [GimnasiosViewController::class, 'show'])->name('gimnasios.show');

    // Reclutados merged into Equipos — old /reclutados redirects to /equipos (see player.php)
    require __DIR__.'/habitats.php';
    require __DIR__.'/player.php';
    require __DIR__.'/exploraciones.php';
    require __DIR__.'/datagrid.php';
    require __DIR__.'/entrenadores.php';
    require __DIR__.'/gimnasios.php';
    // require __DIR__ . '/../src/Crud/routes.php';
});
