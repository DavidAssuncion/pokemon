<?php

declare(strict_types=1);

namespace Src\CombateRuta\Infra\Controllers;

use App\Support\CombateRuta\AlmacenRivalRutaFijado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\CombateRuta\App\CalcularComparacionPcRuta;
use Src\CombateRuta\App\GeneradorEquipoRuta;
use Src\CombateRuta\App\IniciarCombateRuta;
use Src\Habitats\App\ValidadorNivelHabitat;
use Symfony\Component\HttpFoundation\Response;

/**
 * Endpoints del combate de ruta (patrón EntrenadorController):
 * - GET  /api/habitats/{habitat}/ruta/rivales  → rivales salvajes del panel.
 * - POST /api/habitats/{habitat}/ruta/iniciar   → crea la batalla 3v3.
 */
final class CombateRutaController
{
    public function __construct(
        private readonly GeneradorEquipoRuta $generadorEquipo,
        private readonly IniciarCombateRuta $iniciarCombate,
        private readonly CalcularComparacionPcRuta $calcularComparacionPc,
        private readonly AlmacenRivalRutaFijado $almacenRival,
        private readonly ValidadorNivelHabitat $validadorNivelHabitat,
    ) {
    }

    public function pc(int $habitat, Request $request): JsonResponse
    {
        $data = Validator::make(
            array_merge($request->only(['team_id', 'nivel', 'preview_id']), ['habitat' => $habitat]),
            [
                'habitat' => ['required', 'integer', Rule::exists('habitats', 'id')],
                'team_id' => ['required', 'integer', Rule::exists('teams', 'id')->where('user_id', Auth::id())],
                'nivel' => ['sometimes', 'integer', 'min:1', 'max:3'],
                'preview_id' => ['sometimes', 'string', 'max:64'],
            ],
        )->validate();

        $nivel = (int) ($data['nivel'] ?? 1);
        $user = Auth::user();

        $this->validadorNivelHabitat->asegurar($habitat, $nivel, $user->nivel());

        $comparacion = $this->calcularComparacionPc->calcular(
            habitatId: $habitat,
            nivel: $nivel,
            teamId: (int) $data['team_id'],
            userId: $user->id,
            nivelJugador: $user->nivel(),
            previewId: $data['preview_id'] ?? null,
        );

        return response()->json(array_merge($comparacion->toArray(), [
            'contexto' => [
                'tipo' => 'ruta',
                'habitat_id' => $habitat,
                'nivel' => $nivel,
            ],
        ]));
    }

    // DEPRECATED: el frontend deja de consumir la preview del encuentro (GET /ruta/rivales).
    /**
     * @deprecated El frontend ya no consume la preview del encuentro; el endpoint
     *             se mantiene sin cambios hasta su eliminación.
     */
    public function rivales(int $habitat, Request $request): JsonResponse
    {
        $nivel = $request->integer('nivel', 1);
        $user = Auth::user();

        $nivel = max(1, min(3, $nivel));
        $this->validadorNivelHabitat->asegurar($habitat, $nivel, $user->nivel());

        $rivales = $this->generadorEquipo->generar($habitat, $nivel, $user->nivel());

        return response()->json([
            'rivales' => array_map(
                fn (DatosPokemonBatalla $pokemon): array => [
                    'id' => $pokemon->id,
                    'nombre' => $pokemon->nombre,
                    'posicion' => $pokemon->posicion->value,
                    'species_id' => $pokemon->speciesId,
                    'nivel' => $pokemon->nivel,
                ],
                $rivales->all(),
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
            'preview_id' => 'sometimes|string|max:64',
        ]);

        $data = $validator->validated();

        $user = Auth::user();
        $nivel = (int) ($data['nivel'] ?? 1);

        $this->validadorNivelHabitat->asegurar($habitat, $nivel, $user->nivel());

        $battleId = $this->iniciarCombate->iniciar(
            habitatId: $habitat,
            nivel: $nivel,
            teamId: (int) $data['team_id'],
            userId: $user->id,
            nivelJugador: $user->nivel(),
            formacion: (array) ($data['formacion'] ?? []),
            previewId: $data['preview_id'] ?? null,
        );

        return response()->json([
            'battle_id' => $battleId,
            'redirect' => url('/combate?battle_id='.$battleId),
        ]);
    }

    public function limpiarPreview(int $habitat, string $previewId): Response
    {
        Validator::make(
            ['habitat' => $habitat, 'preview_id' => $previewId],
            [
                'habitat' => ['required', 'integer', Rule::exists('habitats', 'id')],
                'preview_id' => ['required', 'string', 'regex:/^[a-f0-9]{32}$/'],
            ],
        )->validate();

        $this->almacenRival->limpiar((int) Auth::id(), $previewId);

        return response()->noContent();
    }
}
