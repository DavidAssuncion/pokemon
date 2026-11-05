<?php

declare(strict_types=1);

namespace Src\Granjas\Infra\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\JsonResponse;
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

    public function show(int $habitat): JsonResponse
    {
        return $this->respuesta($habitat);
    }

    public function bayas(int $habitat): JsonResponse
    {
        return response()->json([
            'bayas' => $this->obtenerCatalogoBayas->handle($this->userId(), $habitat)->toArray(),
        ]);
    }

    public function mejorarNido(int $habitat, int $chain): JsonResponse
    {
        $this->acciones->nido($this->userId(), $habitat, $chain);

        return $this->respuesta($habitat);
    }

    public function mejorarRefugio(int $habitat, int $chain): JsonResponse
    {
        $this->acciones->refugio($this->userId(), $habitat, $chain);

        return $this->respuesta($habitat);
    }

    public function alternarCondiciones(int $habitat, int $chain): JsonResponse
    {
        $this->acciones->condiciones($this->userId(), $habitat, $chain);

        return $this->respuesta($habitat);
    }

    public function mejorarComplemento(int $habitat, int $chain, string $stat): JsonResponse
    {
        $this->acciones->complemento($this->userId(), $habitat, $chain, $stat);

        return $this->respuesta($habitat);
    }

    public function plantar(int $habitat, int $chain, PlantarBayaRequest $request): JsonResponse
    {
        /** @var array{slot:int|string, berry:string} $datos */
        $datos = $request->validated();

        $this->plantarBaya->handle(
            $this->userId(),
            $habitat,
            $chain,
            (int) $datos['slot'],
            $datos['berry'],
        );

        return $this->respuesta($habitat);
    }

    public function cosechar(int $habitat, int $chain, int $slot): JsonResponse
    {
        $this->cosecharPlantacion->handle($this->userId(), $habitat, $chain, $slot);

        return $this->respuesta($habitat);
    }

    public function cosecharTodo(): JsonResponse
    {
        return response()->json($this->cosecharTodo->handle($this->userId())->toArray());
    }

    private function respuesta(int $habitat): JsonResponse
    {
        return response()->json(
            $this->obtenerGranja->handle($this->userId(), $habitat)->toArray(),
        );
    }

    private function userId(): int
    {
        return (int) $this->auth->id();
    }
}
