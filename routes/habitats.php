<?php

use App\Http\Controllers\HabitatsController;
use Illuminate\Support\Facades\Route;
use Src\Habitats\App\ObtenerPokemonsPorHabitat;
use Src\Habitats\Infra\HabitatRepository;

Route::get('/habitats/{id}', [HabitatsController::class, 'show']);

Route::get('/api/habitats/{habitat}/pokemon', function (int $habitat) {
    $useCase = new ObtenerPokemonsPorHabitat(new HabitatRepository());
    return response()->json($useCase->handle($habitat));
});


Route::get('/habitats-img/{id}.webp', function (int $id) {
    $path = resource_path("habitats_img/{$id}.webp");
    if (!file_exists($path)) {
        abort(404);
    }

    return response()->file($path, [
        'Content-Type' => 'image/webp',
    ]);
});