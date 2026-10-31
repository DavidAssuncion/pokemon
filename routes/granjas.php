<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Granjas\Infra\Controllers\GranjaController;

// Granjas — ver src/Granjas/
Route::get('/api/habitats/{habitat}/farm', [GranjaController::class, 'show'])->whereNumber('habitat');
Route::post('/api/habitats/{habitat}/farm/nests/{chain}/upgrade', [GranjaController::class, 'mejorarNido'])
    ->whereNumber('habitat')->whereNumber('chain');
Route::post('/api/habitats/{habitat}/farm/shelters/{chain}/upgrade', [GranjaController::class, 'mejorarRefugio'])
    ->whereNumber('habitat')->whereNumber('chain');
Route::post('/api/habitats/{habitat}/farm/shelters/{chain}/toggle-unhealthy', [GranjaController::class, 'alternarCondiciones'])
    ->whereNumber('habitat')->whereNumber('chain');
Route::post('/api/habitats/{habitat}/farm/shelters/{chain}/complements/{stat}/upgrade', [GranjaController::class, 'mejorarComplemento'])
    ->whereNumber('habitat')->whereNumber('chain');
