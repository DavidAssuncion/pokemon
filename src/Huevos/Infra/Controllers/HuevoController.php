<?php

declare(strict_types=1);

namespace Src\Huevos\Infra\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Huevo;
use App\Support\ReclutadoSerializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Huevos\App\CrearHuevo;
use Src\Huevos\App\EclosionarHuevo;
use Src\Huevos\App\ListarHuevos;

final class HuevoController extends Controller
{
    public function __construct(
        private readonly ListarHuevos $listar,
        private readonly CrearHuevo $crear,
        private readonly EclosionarHuevo $eclosionar,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json($this->listar->listar((int) auth()->id()));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'evolution_chain_id' => 'required|integer|min:1',
        ]);

        $huevo = $this->crear->crear(
            userId: (int) auth()->id(),
            evolutionChainId: (int) $data['evolution_chain_id'],
        );

        return response()->json([
            'huevo' => $this->listar->serializarUno($huevo),
        ], 201);
    }

    public function eclosionar(Huevo $huevo): JsonResponse
    {
        $reclutado = $this->eclosionar->eclosionar($huevo, (int) auth()->id());

        return response()->json([
            'reclutado' => ReclutadoSerializer::serializar($reclutado),
        ]);
    }
}
