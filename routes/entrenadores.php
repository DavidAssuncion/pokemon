<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\CombateEntrenadores\Infra\Controllers\EntrenadorController;

Route::get('/api/habitats/{habitat}/entrenadores', [EntrenadorController::class, 'index']);
Route::post('/api/habitats/{habitat}/entrenadores/{nivel}/{trainer}/combatir', [EntrenadorController::class, 'combatir']);
