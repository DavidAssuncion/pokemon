<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class IconoController extends Controller
{
    public function show(Request $request, string $filename): \Illuminate\Http\Response
    {
        if (! preg_match('/^[a-zA-Z0-9]+(?:[_-][a-zA-Z0-9]+)*\.png$/', $filename)) {
            abort(404);
        }

        $path = resource_path("iconos/{$filename}");
        if (! file_exists($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type' => 'image/png',
        ]);
    }

    public function shiny(Request $request, string $filename): \Illuminate\Http\Response
    {
        if (! preg_match('/^[a-zA-Z0-9]+(?:[_-][a-zA-Z0-9]+)*\.png$/', $filename)) {
            abort(404);
        }

        $path = resource_path("iconos/shiny/{$filename}");
        if (! file_exists($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type' => 'image/png',
        ]);
    }
}
