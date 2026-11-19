<?php

declare(strict_types=1);

namespace Src\Granjas\Infra\Controllers;

use App\Http\Controllers\Controller;
use App\Support\DesbloqueoPokedex;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Granjas\App\AccionesGranja;
use Src\Granjas\App\CosecharPlantacion;
use Src\Granjas\App\CosecharTodo;
use Src\Granjas\App\ObtenerCatalogoBayas;
use Src\Granjas\App\ObtenerGranja;
use Src\Granjas\App\PlantarBaya;
use Src\Granjas\Infra\Requests\PlantarBayaRequest;

final class GranjaController extends Controller
{
    public function __construct(
        private readonly ObtenerGranja $obtenerGranja,
        private readonly AccionesGranja $acciones,
        private readonly PlantarBaya $plantarBaya,
        private readonly CosecharPlantacion $cosecharPlantacion,
        private readonly CosecharTodo $cosecharTodo,
        private readonly ObtenerCatalogoBayas $obtenerCatalogoBayas,
        private readonly Guard $auth,
    ) {
    }

    public function show(Request $request, int $habitat): JsonResponse
    {
        return $this->respuesta($habitat, DesbloqueoPokedex::activo($request));
    }

    public function bayas(int $habitat): JsonResponse
    {
        return response()->json([
            'bayas' => $this->obtenerCatalogoBayas->handle($this->userId(), $habitat)->toArray(),
        ]);
    }

    public function mejorarNido(Request $request, int $habitat, int $chain): JsonResponse
    {
        $incluirNoAvistadas = DesbloqueoPokedex::activo($request);

        $this->acciones->nido($this->userId(), $habitat, $chain, $incluirNoAvistadas);

        return $this->respuesta($habitat, $incluirNoAvistadas);
    }

    public function mejorarRefugio(Request $request, int $habitat, int $chain): JsonResponse
    {
        $incluirNoAvistadas = DesbloqueoPokedex::activo($request);

        $this->acciones->refugio($this->userId(), $habitat, $chain, $incluirNoAvistadas);

        return $this->respuesta($habitat, $incluirNoAvistadas);
    }

    public function alternarCondiciones(Request $request, int $habitat, int $chain): JsonResponse
    {
        $incluirNoAvistadas = DesbloqueoPokedex::activo($request);

        $this->acciones->condiciones($this->userId(), $habitat, $chain, $incluirNoAvistadas);

        return $this->respuesta($habitat, $incluirNoAvistadas);
    }

    public function mejorarComplemento(Request $request, int $habitat, int $chain, string $stat): JsonResponse
    {
        $incluirNoAvistadas = DesbloqueoPokedex::activo($request);

        $this->acciones->complemento($this->userId(), $habitat, $chain, $stat, $incluirNoAvistadas);

        return $this->respuesta($habitat, $incluirNoAvistadas);
    }

    public function plantar(int $habitat, int $chain, PlantarBayaRequest $request): JsonResponse
    {
        /** @var array{slot:int|string, berry:string} $datos */
        $datos = $request->validated();
        $incluirNoAvistadas = DesbloqueoPokedex::activo($request);

        $this->plantarBaya->handle(
            $this->userId(),
            $habitat,
            $chain,
            (int) $datos['slot'],
            $datos['berry'],
            $incluirNoAvistadas,
        );

        return $this->respuesta($habitat, $incluirNoAvistadas);
    }

    public function cosechar(Request $request, int $habitat, int $chain, int $slot): JsonResponse
    {
        $incluirNoAvistadas = DesbloqueoPokedex::activo($request);

        $this->cosecharPlantacion->handle($this->userId(), $habitat, $chain, $slot, $incluirNoAvistadas);

        return $this->respuesta($habitat, $incluirNoAvistadas);
    }

    public function cosecharTodo(): JsonResponse
    {
        return response()->json(
            $this->cosecharTodo->handle(
                $this->userId(),
                (bool) config('pokedex.unlock_all'),
            )->toArray(),
        );
    }

    private function respuesta(int $habitat, bool $incluirNoAvistadas): JsonResponse
    {
        return response()->json(
            $this->obtenerGranja->handle($this->userId(), $habitat, $incluirNoAvistadas)->toArray(),
        );
    }

    private function userId(): int
    {
        return (int) $this->auth->id();
    }
}
