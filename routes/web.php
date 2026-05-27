<?php

use Illuminate\Support\Facades\Route;
use Src\Habitats\App\ObtenerHabitatsPorProvincia;
use Src\Habitats\App\ObtenerHabitatDetalle;
use Src\Habitats\App\ObtenerPokemonsPorHabitat;
use Src\Habitats\Infra\HabitatRepository;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/habitats', function () {
    $useCase = new ObtenerHabitatsPorProvincia(new HabitatRepository());
    $provincias = $useCase->handle()->toArray();

    return view('habitats.index', ['provincias' => $provincias]);
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

Route::get('/iconos/{filename}', function (string $filename) {
    if (!preg_match('/^[a-zA-Z0-9]+(?:_[a-zA-Z0-9]+)*\.png$/', $filename)) {
        abort(404);
    }

    $path = resource_path("iconos/{$filename}");
    if (!file_exists($path)) {
        abort(404);
    }

    return response()->file($path, [
        'Content-Type' => 'image/png',
    ]);
});

Route::get('/habitats/{id}', function (int $id) {
    $useCase = new ObtenerHabitatDetalle(new HabitatRepository());
    $habitat = $useCase->handle($id);

    if (empty($habitat)) {
        abort(404);
    }

    return view('habitats.show', ['habitat' => $habitat]);
});

Route::get('/api/habitats/{habitat}/pokemon', function (int $habitat) {
    $useCase = new ObtenerPokemonsPorHabitat(new HabitatRepository());
    return response()->json($useCase->handle($habitat));
});

require __DIR__ . '/reclutados.php';
