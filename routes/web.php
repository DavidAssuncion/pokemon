<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HabitatsController;
use App\Http\Controllers\IconoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HabitatsController::class, 'index']);
Route::get('/habitats', [HabitatsController::class, 'index']);

Route::get('/iconos/{filename}', [IconoController::class, 'show']);
Route::get('/iconos/shiny/{filename}', [IconoController::class, 'shiny']);

Route::get('/cruds', [DashboardController::class, 'cruds'])->name('cruds');

//Route::get('/combate', \App\Livewire\Combate::class);

require __DIR__.'/reclutados.php';
require __DIR__.'/habitats.php';
// require __DIR__ . '/../src/Crud/routes.php';
