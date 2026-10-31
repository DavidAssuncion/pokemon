<?php

declare(strict_types=1);

namespace Src\Granjas\Infra\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\JsonResponse;
use Src\Granjas\App\AccionesGranja;
use Src\Granjas\App\ObtenerGranja;

final class GranjaController extends Controller
{
    public function __construct(
        private readonly ObtenerGranja $obtenerGranja,
        private readonly AccionesGranja $acciones,
        private readonly Guard $auth,
    ) {
    }

    public function show(int $habitat): JsonResponse
    {
        return $this->respuesta($habitat);
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
