<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\CombateRuta\Infra\Controllers\CombateRutaController;

// Combate de ruta 3v3 — ver src/CombateRuta/
Route::get('/api/habitats/{habitat}/ruta/rivales', [CombateRutaController::class, 'rivales'])
    ->whereNumber('habitat');
Route::post('/api/habitats/{habitat}/ruta/iniciar', [CombateRutaController::class, 'iniciar'])
    ->whereNumber('habitat');
Route::get('/api/habitats/{habitat}/ruta/pc', [CombateRutaController::class, 'pc'])
    ->whereNumber('habitat');
Route::delete('/api/habitats/{habitat}/ruta/preview/{previewId}', [CombateRutaController::class, 'limpiarPreview'])
    ->whereNumber('habitat')
    ->where('previewId', '[a-f0-9]{32}');
