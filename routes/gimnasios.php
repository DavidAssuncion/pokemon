<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Gimnasios\Infra\Controllers\GimnasioController;

Route::get('/api/gimnasios', [GimnasioController::class, 'index']);
Route::get('/api/gimnasios/{gym}', [GimnasioController::class, 'show']);
Route::post('/api/gimnasios/{gym}/combatir', [GimnasioController::class, 'combatir']);
