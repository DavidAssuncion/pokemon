<?php

declare(strict_types=1);

use App\Http\Controllers\ExploracionActivaController;
use Illuminate\Support\Facades\Route;

Route::get('/exploraciones', [ExploracionActivaController::class, 'index']);
Route::post('/exploraciones', [ExploracionActivaController::class, 'store']);
Route::post('/exploraciones/{exploracion}/recoger', [ExploracionActivaController::class, 'recoger']);
Route::post('/exploraciones/{exploracion}/cerrar', [ExploracionActivaController::class, 'cerrar']);
