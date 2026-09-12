<?php

declare(strict_types=1);

namespace Src\Mazmorras\Infra\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Src\Mazmorras\App\IniciarCombateMazmorra;
use Src\Mazmorras\App\ObtenerPisosMazmorra;

class MazmorraController extends Controller
{
    public function __construct(
        private readonly ObtenerPisosMazmorra $obtenerPisos,
        private readonly IniciarCombateMazmorra $iniciarCombate,
    ) {
    }

    public function pisos(int $habitat): JsonResponse
    {
        $user = Auth::user();

        return response()->json($this->obtenerPisos->obtener(
            habitatId: $habitat,
            userId: $user->id,
        )->toArray());
    }

    public function combatir(int $habitat, int $piso, Request $request): JsonResponse
    {
        $data = $request->validate([
            'team_id' => ['required', 'integer', Rule::exists('teams', 'id')->where('user_id', Auth::id())],
            'formacion' => 'sometimes|array',
            'formacion.*' => 'in:vanguardia,retaguardia',
        ]);

        $user = Auth::user();

        $battleId = $this->iniciarCombate->iniciar(
            habitatId: $habitat,
            piso: $piso,
            teamId: (int) $data['team_id'],
            userId: (int) $user->id,
            nivelJugador: $user->nivel(),
            formacion: (array) ($data['formacion'] ?? []),
        );

        return response()->json([
            'battle_id' => $battleId,
            'redirect' => url('/combate?battle_id='.$battleId),
        ]);
    }
}
