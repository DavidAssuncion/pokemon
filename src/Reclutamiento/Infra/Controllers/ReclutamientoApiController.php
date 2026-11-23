<?php

declare(strict_types=1);

namespace Src\Reclutamiento\Infra\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Src\Reclutamiento\App\ConstructorReclutablesResumen;

/**
 * Colección compacta de reclutables del usuario, ya ordenada por `updated_at`
 * descendente y con el icono resuelto por el backend.
 */
final class ReclutamientoApiController
{
    public function __construct(
        private readonly ConstructorReclutablesResumen $reclutables,
    ) {
    }

    public function index(): JsonResponse
    {
        $reclutables = $this->reclutables->todos((int) Auth::id());

        return response()->json([
            'reclutables' => $reclutables->toArray(),
            'total' => $reclutables->count(),
        ], 200, ['Cache-Control' => 'no-store, private']);
    }
}
