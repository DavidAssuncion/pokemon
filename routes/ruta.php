<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\CombateRuta\Infra\Controllers\CombateRutaController;

// Combate de ruta 3v3 — ver src/CombateRuta/
Route::get('/api/habitats/{habitat}/ruta/rivales', [CombateRutaController::class, 'rivales'])
    ->whereNumber('habitat');
Route::post('/api/habitats/{habitat}/ruta/iniciar', [CombateRutaController::class, 'iniciar'])
    ->whereNumber('habitat');
