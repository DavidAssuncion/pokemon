<?php

declare(strict_types=1);

namespace Src\Forja\Infra\Controllers;

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Src\Forja\App\DataTransferObjects\RecetaDetalle;
use Src\Forja\App\Fabricar;
use Src\Forja\App\ListarRecetas;
use Src\Forja\Infra\Requests\FabricarRequest;

final class ForjaController
{
    public function __construct(
        private readonly ListarRecetas $listarRecetas,
        private readonly Fabricar $fabricar,
        private readonly Guard $auth,
        private readonly Factory $views,
    ) {
    }

    public function index(): View
    {
        return $this->views->make('forja.index');
    }

    public function recetas(): JsonResponse
    {
        $recetas = $this->listarRecetas->handle($this->userId())->map(
            static fn (RecetaDetalle $receta): array => $receta->toArray(),
        );

        return response()->json(['recetas' => $recetas]);
    }

    public function fabricar(FabricarRequest $request): JsonResponse
    {
        /** @var array{receta:string, cantidad?:int|string} $datos */
        $datos = $request->validated();

        $resultado = $this->fabricar->handle(
            $this->userId(),
            $datos['receta'],
            isset($datos['cantidad']) ? (int) $datos['cantidad'] : 1,
        );

        return response()->json($resultado->toArray());
    }

    private function userId(): int
    {
        return (int) $this->auth->id();
    }
}
