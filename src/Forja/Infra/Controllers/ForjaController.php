<?php

declare(strict_types=1);

namespace Src\Forja\Infra\Controllers;

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Src\Forja\App\DataTransferObjects\RecetaDetalle;
use Src\Forja\App\Fabricar;
use Src\Forja\App\FabricarPieza;
use Src\Forja\App\ListarRecetas;
use Src\Forja\Domain\Piezas\CatalogoPiezas;
use Src\Forja\Infra\Requests\FabricarRequest;

final class ForjaController
{
    /**
     * Secciones de la Forja en orden de presentación. El backend es la fuente
     * de verdad de nombres y orden; el frontend solo pinta.
     *
     * @var list<array{clave: string, nombre: string, orden: int}>
     */
    private const SECCIONES = [
        ['clave' => 'piezas', 'nombre' => 'Piezas de tus nidos', 'orden' => 0],
        ['clave' => 'medicinas', 'nombre' => 'Medicinas', 'orden' => 1],
        ['clave' => 'mejoras', 'nombre' => 'Mejoras', 'orden' => 2],
        ['clave' => 'reductores', 'nombre' => 'Reductores', 'orden' => 3],
        ['clave' => 'resistencias', 'nombre' => 'Resistencias', 'orden' => 4],
    ];

    public function __construct(
        private readonly ListarRecetas $listarRecetas,
        private readonly Fabricar $fabricar,
        private readonly FabricarPieza $fabricarPieza,
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
        $recetas = [];

        foreach ($this->listarRecetas->handle($this->userId())->toList() as $detalle) {
            if ($detalle instanceof RecetaDetalle) {
                $recetas[] = $detalle->toArray();
            }
        }

        return response()->json([
            'secciones' => self::SECCIONES,
            'recetas' => $recetas,
        ]);
    }

    public function fabricar(FabricarRequest $request): JsonResponse
    {
        /** @var array{receta:string, cantidad?:int|string} $datos */
        $datos = $request->validated();
        $cantidad = isset($datos['cantidad']) ? (int) $datos['cantidad'] : 1;

        $resultado = CatalogoPiezas::esRecetaDePieza($datos['receta'])
            ? $this->fabricarPieza->handle($this->userId(), $datos['receta'], $cantidad)
            : $this->fabricar->handle($this->userId(), $datos['receta'], $cantidad);

        return response()->json($resultado->toArray());
    }

    private function userId(): int
    {
        return (int) $this->auth->id();
    }
}
