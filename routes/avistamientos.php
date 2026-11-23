<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Avistamientos\Infra\Controllers\AvistamientoController;

// Avistamientos — evento global temporal (ventana de 3 días). El modal de la
// navbar consume este endpoint al abrirse; no hay página propia.
Route::get('/api/avistamientos/vigente', [AvistamientoController::class, 'vigente'])
    ->name('avistamientos.vigente');
