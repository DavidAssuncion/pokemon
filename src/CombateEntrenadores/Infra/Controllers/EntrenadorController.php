<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\Infra\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Src\CombateEntrenadores\App\IniciarCombateEntrenador;
use Src\CombateEntrenadores\App\ObtenerEntrenadoresHabitat;

class EntrenadorController extends Controller
{
    public function __construct(
        private readonly ObtenerEntrenadoresHabitat $obtenerEntrenadores,
        private readonly IniciarCombateEntrenador $iniciarCombate,
    ) {
    }

    public function index(int $habitat): JsonResponse
    {
        $entrenadores = $this->obtenerEntrenadores->obtener(
            $habitat,
            (int) Auth::id(),
            today()->toDateString(),
        );

        return response()->json($entrenadores);
    }

    public function combatir(int $habitat, int $nivel, int $trainer, Request $request): JsonResponse
    {
        $data = $request->validate([
            'team_id' => ['required', 'integer', Rule::exists('teams', 'id')->where('user_id', Auth::id())],
            'formacion' => 'sometimes|array',
            'formacion.*' => 'in:vanguardia,retaguardia',
        ]);

        $battleId = $this->iniciarCombate->iniciar(
            habitatId: $habitat,
            nivel: $nivel,
            trainerIndex: $trainer,
            teamId: (int) $data['team_id'],
            userId: (int) Auth::id(),
            fecha: today()->toDateString(),
            formacion: (array) ($data['formacion'] ?? []),
        );

        return response()->json([
            'battle_id' => $battleId,
            'redirect' => url('/combate?battle_id='.$battleId),
        ]);
    }
}
