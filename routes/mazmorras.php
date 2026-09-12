<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Mazmorras\Infra\Controllers\MazmorraController;

Route::get('/api/habitats/{habitat}/mazmorra', [MazmorraController::class, 'pisos'])->whereNumber('habitat');
Route::post('/api/habitats/{habitat}/mazmorra/{piso}/combatir', [MazmorraController::class, 'combatir'])
    ->whereNumber('habitat')
    ->whereNumber('piso');
