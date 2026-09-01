<?php

declare(strict_types=1);

namespace Src\Gimnasios\Infra\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Src\Gimnasios\App\IniciarCombateGimnasio;
use Src\Gimnasios\App\ObtenerGimnasioDetalle;
use Src\Gimnasios\App\ObtenerGimnasios;

class GimnasioController extends Controller
{
    public function __construct(
        private readonly ObtenerGimnasios $obtenerGimnasios,
        private readonly ObtenerGimnasioDetalle $obtenerDetalle,
        private readonly IniciarCombateGimnasio $iniciarCombate,
    ) {
    }

    public function index(): JsonResponse
    {
        $user = Auth::user();

        $gimnasios = $this->obtenerGimnasios->obtener(
            $user->id,
            $user->nivel(),
        );

        return response()->json($gimnasios);
    }

    public function show(string $gym): JsonResponse
    {
        $user = Auth::user();

        $detalle = $this->obtenerDetalle->obtener(
            $gym,
            $user->id,
            $user->nivel(),
        );

        return response()->json($detalle);
    }

    public function combatir(string $gym, Request $request): JsonResponse
    {
        $data = $request->validate([
            'team_id' => ['required', 'integer', Rule::exists('teams', 'id')->where('user_id', Auth::id())],
            'formacion' => 'sometimes|array',
            'formacion.*' => 'in:vanguardia,retaguardia',
        ]);

        $user = Auth::user();

        $battleId = $this->iniciarCombate->iniciar(
            gymSlug: $gym,
            teamId: (int) $data['team_id'],
            userId: $user->id,
            nivelJugador: $user->nivel(),
            formacion: (array) ($data['formacion'] ?? []),
        );

        return response()->json([
            'battle_id' => $battleId,
            'redirect' => url('/combate?battle_id='.$battleId),
        ]);
    }
}
