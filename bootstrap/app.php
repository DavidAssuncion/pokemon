<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (\Src\Shared\Domain\Exceptions\RecursoNoExiste $e, \Illuminate\Http\Request $request) {
            return response()->json(['message' => $e->getMessage()], 404);
        });
        $exceptions->renderable(function (\Src\Shared\Domain\Exceptions\ViolacionReglaNegocio $e, \Illuminate\Http\Request $request) {
            return response()->json(['message' => $e->getMessage()], 422);
        });
        $exceptions->renderable(function (\Src\Shared\Domain\Exceptions\DominioExcepcion $e, \Illuminate\Http\Request $request) {
            return response()->json(['message' => $e->getMessage()], 400);
        });
    })->create();
