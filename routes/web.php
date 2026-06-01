<?php

use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return view('welcome');
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


require __DIR__ . '/reclutados.php';
require __DIR__ . '/habitats.php';
