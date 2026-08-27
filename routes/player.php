<?php

declare(strict_types=1);

use App\Http\Controllers\PlayerController;
use Illuminate\Support\Facades\Route;

Route::get('/pokedex', [PlayerController::class, 'pokedex']);
Route::get('/reclutamiento', [PlayerController::class, 'reclutamiento']);
Route::get('/equipos', [PlayerController::class, 'equipos']);
