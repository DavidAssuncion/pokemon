<?php

declare(strict_types=1);

use App\Http\Controllers\HabitatsController;
use Illuminate\Support\Facades\Route;

Route::get('/habitats/{id}', [HabitatsController::class, 'show']);

Route::get('/api/habitats/{habitat}/pokemon', [HabitatsController::class, 'pokemon']);

Route::get('/habitats-img/{id}.webp', function (int $id) {
    $path = resource_path("habitats_img/{$id}.webp");
    if (! file_exists($path)) {
        abort(404);
    }

    return response()->file($path, [
        'Content-Type' => 'image/webp',
    ]);
});

Route::get('/api/habitats/{id}/families', [HabitatsController::class, 'families']);
Route::post('/api/habitats/{id}/families', [HabitatsController::class, 'assignFamily']);
Route::delete('/api/habitats/{id}/families/{chainId}', [HabitatsController::class, 'removeFamily']);
Route::patch('/api/habitats/{habitat}/pokemon/{pokemon}', [HabitatsController::class, 'movePokemonLevel']);

Route::get('/api/habitats/unassigned-families', [HabitatsController::class, 'unassignedFamilies']);

Route::post('/api/habitats/{habitat}/toggle-favorito', [HabitatsController::class, 'toggleFavorito']);
