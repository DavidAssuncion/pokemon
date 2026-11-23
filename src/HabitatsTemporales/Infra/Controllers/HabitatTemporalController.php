<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\Infra\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Src\HabitatsTemporales\App\CalcularComparacionPcHabitatTemporal;
use Src\HabitatsTemporales\App\IniciarCombateHabitatTemporal;
use Src\HabitatsTemporales\App\ObtenerDetalleFaccion;

/**
 * API de los hábitats temporales de facciones rivales.
 */
final class HabitatTemporalController
{
    public function __construct(
        private readonly ObtenerDetalleFaccion $obtenerDetalle,
        private readonly IniciarCombateHabitatTemporal $iniciarCombate,
        private readonly CalcularComparacionPcHabitatTemporal $calcularComparacionPc,
    ) {
    }

    public function show(string $faccion): JsonResponse
    {
        $detalle = $this->obtenerDetalle->obtener($faccion, (int) Auth::id());

        return response()->json($detalle->toArray());
    }

    public function pc(string $faccion, Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'team_id' => ['sometimes', 'integer', Rule::exists('teams', 'id')->where('user_id', Auth::id())],
        ])->validate();

        $user = Auth::user();

        $comparacion = $this->calcularComparacionPc->calcular(
            faccionSlug: $faccion,
            teamId: isset($data['team_id']) ? (int) $data['team_id'] : null,
            userId: $user->id,
            nivelJugador: $user->nivel(),
        );

        return response()->json(array_merge($comparacion->toArray(), [
            'contexto' => [
                'tipo' => 'habitat_temporal',
                'faccion' => $faccion,
            ],
        ]));
    }

    public function combatir(string $faccion, int $oleada, int $combate, Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'team_id' => ['required', 'integer', Rule::exists('teams', 'id')->where('user_id', Auth::id())],
            'formacion' => 'sometimes|array',
            'formacion.*' => 'in:vanguardia,retaguardia',
        ])->validate();

        $user = Auth::user();

        $battleId = $this->iniciarCombate->iniciar(
            faccionSlug: $faccion,
            oleada: $oleada,
            combate: $combate,
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
