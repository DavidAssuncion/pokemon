<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\Infra\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Src\CombateEntrenadores\App\CalcularComparacionPcEntrenador;
use Src\CombateEntrenadores\App\IniciarCombateEntrenador;
use Src\CombateEntrenadores\App\ObtenerEntrenadoresHabitat;

class EntrenadorController extends Controller
{
    public function __construct(
        private readonly ObtenerEntrenadoresHabitat $obtenerEntrenadores,
        private readonly IniciarCombateEntrenador $iniciarCombate,
        private readonly CalcularComparacionPcEntrenador $calcularComparacionPc,
    ) {
    }

    public function pc(int $habitat, int $nivel, int $trainer, Request $request): JsonResponse
    {
        $data = Validator::make(
            array_merge($request->only(['team_id']), [
                'habitat' => $habitat,
                'nivel' => $nivel,
                'trainer' => $trainer,
            ]),
            [
                'habitat' => ['required', 'integer', Rule::exists('habitats', 'id')],
                'team_id' => ['required', 'integer', Rule::exists('teams', 'id')->where('user_id', Auth::id())],
                'nivel' => ['required', 'integer', 'min:1', 'max:3'],
                'trainer' => ['required', 'integer', 'min:1', 'max:3'],
            ],
        )->validate();

        $user = Auth::user();

        $comparacion = $this->calcularComparacionPc->calcular(
            habitatId: $data['habitat'],
            nivel: $data['nivel'],
            trainerIndex: $data['trainer'],
            teamId: (int) $data['team_id'],
            userId: $user->id,
            nivelJugador: $user->nivel(),
            fecha: today()->toDateString(),
        );

        return response()->json(array_merge($comparacion->toArray(), [
            'contexto' => [
                'tipo' => 'entrenador',
                'habitat_id' => $data['habitat'],
                'nivel' => $data['nivel'],
                'entrenador_indice' => $data['trainer'],
            ],
        ]));
    }

    public function index(int $habitat): JsonResponse
    {
        $entrenadores = $this->obtenerEntrenadores->obtener(
            $habitat,
            (int) Auth::id(),
            today()->toDateString(),
        );

        // Frontera: la blade consume el mapa keyed por nivel (trainers[level][i]).
        $porNivel = [];
        foreach ($entrenadores as $nivel) {
            $porNivel[$nivel->nivel] = $nivel->entrenadores->toArray();
        }

        return response()->json($porNivel);
    }

    public function combatir(int $habitat, int $nivel, int $trainer, Request $request): JsonResponse
    {
        $data = $request->validate([
            'team_id' => ['required', 'integer', Rule::exists('teams', 'id')->where('user_id', Auth::id())],
            'formacion' => 'sometimes|array',
            'formacion.*' => 'in:vanguardia,retaguardia',
        ]);

        $user = Auth::user();

        $battleId = $this->iniciarCombate->iniciar(
            habitatId: $habitat,
            nivel: $nivel,
            trainerIndex: $trainer,
            teamId: (int) $data['team_id'],
            userId: $user->id,
            nivelJugador: $user->nivel(),
            fecha: today()->toDateString(),
            formacion: (array) ($data['formacion'] ?? []),
        );

        return response()->json([
            'battle_id' => $battleId,
            'redirect' => url('/combate?battle_id='.$battleId),
        ]);
    }
}
