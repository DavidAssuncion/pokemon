<?php

declare(strict_types=1);

use App\Http\Controllers\HabitatsTemporalesViewController;
use Illuminate\Support\Facades\Route;
use Src\HabitatsTemporales\Infra\Controllers\HabitatTemporalController;

Route::get('/habitats/temporales/{faccion}', [HabitatsTemporalesViewController::class, 'show'])
    ->name('habitats.temporales.show');

Route::get('/api/habitats-temporales/{faccion}', [HabitatTemporalController::class, 'show']);
Route::get('/api/habitats-temporales/{faccion}/pc', [HabitatTemporalController::class, 'pc']);
Route::post('/api/habitats-temporales/{faccion}/oleadas/{oleada}/combates/{combate}/combatir', [HabitatTemporalController::class, 'combatir'])
    ->whereNumber('oleada')
    ->whereNumber('combate');
