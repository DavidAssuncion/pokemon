<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Gimnasios\Infra\Controllers\AdminGymController;
use Src\Gimnasios\Infra\Controllers\GimnasioController;

Route::get('/api/gimnasios', [GimnasioController::class, 'index']);
Route::get('/api/gimnasios/{gym}', [GimnasioController::class, 'show']);
Route::post('/api/gimnasios/{gym}/combatir', [GimnasioController::class, 'combatir']);

// CRUD admin de gyms (sin borrado ni desactivación).
Route::get('/api/admin/gyms', [AdminGymController::class, 'index']);
Route::get('/api/admin/gyms/{slug}', [AdminGymController::class, 'show']);
Route::post('/api/admin/gyms', [AdminGymController::class, 'store']);
Route::put('/api/admin/gyms/{slug}', [AdminGymController::class, 'update']);
Route::put('/api/admin/gyms/{slug}/stages/{etapa}', [AdminGymController::class, 'updateStage']);
