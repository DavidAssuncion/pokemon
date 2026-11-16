<?php

declare(strict_types=1);

namespace Src\Gimnasios\Infra\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Src\Gimnasios\App\CalcularComparacionPcGimnasio;
use Src\Gimnasios\App\IniciarCombateGimnasio;
use Src\Gimnasios\App\ObtenerGimnasioDetalle;
use Src\Gimnasios\App\ObtenerGimnasios;

class GimnasioController extends Controller
{
    public function __construct(
        private readonly ObtenerGimnasios $obtenerGimnasios,
        private readonly ObtenerGimnasioDetalle $obtenerDetalle,
        private readonly IniciarCombateGimnasio $iniciarCombate,
        private readonly CalcularComparacionPcGimnasio $calcularComparacionPc,
    ) {
    }

    public function pc(string $gym, Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'team_id' => ['sometimes', 'integer', Rule::exists('teams', 'id')->where('user_id', Auth::id())],
        ])->validate();

        $user = Auth::user();

        $comparacion = $this->calcularComparacionPc->calcular(
            slug: $gym,
            teamId: isset($data['team_id']) ? (int) $data['team_id'] : null,
            userId: $user->id,
            nivelJugador: $user->nivel(),
        );

        return response()->json(array_merge($comparacion->toArray(), [
            'contexto' => [
                'tipo' => 'gimnasio',
                'gym' => $gym,
            ],
        ]));
    }

    public function index(): JsonResponse
    {
        $user = Auth::user();

        $gimnasios = $this->obtenerGimnasios->obtener(
            $user->id,
            $user->nivel(),
        );

        return response()->json($gimnasios->toArray());
    }

    public function show(string $gym): JsonResponse
    {
        $user = Auth::user();

        $detalle = $this->obtenerDetalle->obtener(
            $gym,
            $user->id,
            $user->nivel(),
        );

        return response()->json($detalle->toArray());
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
