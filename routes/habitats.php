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
