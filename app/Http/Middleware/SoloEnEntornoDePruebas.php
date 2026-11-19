<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Qa\ModoQa;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe las rutas de QA a los entornos local/testing. Fuera de ellos
 * responde 404, también para usuarios anónimos, y se evalúa antes de `auth`.
 */
final class SoloEnEntornoDePruebas
{
    public function handle(Request $request, Closure $next): Response
    {
        ModoQa::asegurarEntornoPermitido();

        return $next($request);
    }
}
