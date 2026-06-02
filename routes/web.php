<?php

use Illuminate\Support\Facades\Route;
use Src\Habitats\App\ObtenerHabitatsPorProvincia;
use Src\Habitats\Infra\HabitatRepository;

Route::get('/habitats', function () {
    $useCase = new ObtenerHabitatsPorProvincia(new HabitatRepository());
    $provincias = $useCase->handle()->toArray();

    return view('habitats.index', ['provincias' => $provincias]);
});
Route::get('/', function () {
    $useCase = new ObtenerHabitatsPorProvincia(new HabitatRepository());
    $provincias = $useCase->handle()->toArray();

    return view('habitats.index', ['provincias' => $provincias]);
});

Route::get('/iconos/{filename}', function (string $filename) {
    if (!preg_match('/^[a-zA-Z0-9]+(?:[_-][a-zA-Z0-9]+)*\.png$/', $filename)) {
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
Route::get('/iconos/shiny/{filename}', function (string $filename) {
    if (!preg_match('/^[a-zA-Z0-9]+(?:[_-][a-zA-Z0-9]+)*\.png$/', $filename)) {
        abort(404);
    }

    $path = resource_path("iconos/shiny/{$filename}");
    if (!file_exists($path)) {
        abort(404);
    }

    return response()->file($path, [
        'Content-Type' => 'image/png',
    ]);
});



Route::get('/cruds', function () {
    $modules = [];
    foreach (glob(resource_path('views/crud/*'), GLOB_ONLYDIR) as $dir) {
        $name = basename($dir);
        if ($name === 'dashboard') continue;
        $modules[] = $name;
    }
    sort($modules);
    return view('crud.dashboard', compact('modules'));
})->name('cruds');

Route::get('/combate', \App\Livewire\Combate::class);

require __DIR__ . '/reclutados.php';
require __DIR__ . '/habitats.php';
// require __DIR__ . '/../src/Crud/routes.php';
