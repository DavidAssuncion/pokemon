<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Pokemon;
use App\Support\ItemCatalogo;
use App\Support\Qa\AjustesQa;
use App\Support\Qa\ModoQa;
use App\Support\Qa\ReclutarDesdePokedex;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Acciones QA del modal de la Pokédex (JSON). Solo se alcanzan en
 * local/testing; el `user_id` siempre sale de la sesión, nunca del cliente.
 */
final class PokedexQaController extends Controller
{
    public function __construct(
        private readonly ReclutarDesdePokedex $reclutar,
        private readonly AjustesQa $ajustes,
    ) {
    }

    public function reclutar(Request $request): JsonResponse
    {
        ModoQa::asegurarEntornoPermitido();

        $datos = Validator::make($request->all(), [
            'pokemon_id' => ['required', 'integer', 'exists:pokemon,id'],
        ])->validate();

        $reclutado = $this->reclutar->para((int) Auth::id(), (int) $datos['pokemon_id']);

        return response()->json([
            'success' => true,
            'mensaje' => 'Pokémon reclutado',
            'reclutado_id' => $reclutado->id,
            'pokemon_id' => $reclutado->pokemon_id,
            'atrapado' => true,
        ]);
    }

    public function carameloFamilia(Request $request): JsonResponse
    {
        ModoQa::asegurarEntornoPermitido();

        $datos = Validator::make($request->all(), [
            'pokemon_id' => ['required', 'integer', 'exists:pokemon,id'],
            'cantidad' => ['required', 'integer', Rule::in(AjustesQa::CANTIDADES_CARAMELOS)],
        ])->validate();

        $pokemon = Pokemon::query()->findOrFail((int) $datos['pokemon_id']);
        $chainId = $pokemon->evolution_chain_id ?? 0;

        if ($chainId < 1) {
            return response()->json(['message' => 'Este Pokémon no tiene familia evolutiva'], 422);
        }

        $clave = ItemCatalogo::keyFamilia($chainId);
        $resultado = $this->ajustes->ajustarInventario(
            (int) Auth::id(),
            $clave,
            (int) $datos['cantidad'],
        );
        $resuelto = ItemCatalogo::resolve($clave);

        return response()->json([
            'success' => true,
            'mensaje' => sprintf('Familia %s: %d caramelos', $resuelto->nombre, $resultado->actual),
            'item_key' => $clave,
            'evolution_chain_id' => $chainId,
            'nombre' => $resuelto->nombre,
            'imagen' => $resuelto->imagen,
            'aplicado' => $resultado->aplicado,
            'cantidad' => $resultado->actual,
        ]);
    }
}
