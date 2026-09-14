<?php

declare(strict_types=1);

namespace Src\CombateRuta\Infra\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\CombateRuta\App\GeneradorEquipoRuta;
use Src\CombateRuta\App\IniciarCombateRuta;

/**
 * Endpoints del combate de ruta (patrón EntrenadorController):
 * - GET  /api/habitats/{habitat}/ruta/rivales  → rivales salvajes del panel.
 * - POST /api/habitats/{habitat}/ruta/iniciar   → crea la batalla 5v5.
 */
final class CombateRutaController extends Controller
{
    public function __construct(
        private readonly GeneradorEquipoRuta $generadorEquipo,
        private readonly IniciarCombateRuta $iniciarCombate,
    ) {
    }

    public function rivales(int $habitat, Request $request): JsonResponse
    {
        $nivel = $request->integer('nivel', 1);
        $user = Auth::user();

        $rivales = $this->generadorEquipo->generar($habitat, max(1, min(3, $nivel)), $user->nivel());

        return response()->json([
            'rivales' => array_map(
                fn (DatosPokemonBatalla $pokemon): array => [
                    'id' => $pokemon->id,
                    'nombre' => $pokemon->nombre,
                    'posicion' => $pokemon->posicion->value,
                    'species_id' => $pokemon->speciesId,
                    'nivel' => $pokemon->nivel,
                ],
                $rivales,
            ),
        ]);
    }

    public function iniciar(int $habitat, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'team_id' => ['required', 'integer', Rule::exists('teams', 'id')->where('user_id', Auth::id())],
            'formacion' => 'sometimes|array',
            'formacion.*' => 'in:vanguardia,retaguardia',
            'nivel' => 'sometimes|integer|min:1|max:3',
        ]);

        $data = $validator->validated();

        $user = Auth::user();
        $nivel = (int) ($data['nivel'] ?? 1);

        $battleId = $this->iniciarCombate->iniciar(
            habitatId: $habitat,
            nivel: $nivel,
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
