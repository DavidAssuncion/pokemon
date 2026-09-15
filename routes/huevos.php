<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Huevos\Infra\Controllers\HuevoController;

// Huevos — ver src/Huevos/
Route::get('/api/huevos', [HuevoController::class, 'index']);
Route::post('/api/huevos', [HuevoController::class, 'store']);
Route::post('/api/huevos/{huevo}/eclosionar', [HuevoController::class, 'eclosionar']);
