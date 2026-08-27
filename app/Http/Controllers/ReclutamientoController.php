<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Caramelo;
use App\Models\Reclutable;
use App\Models\Reclutado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReclutamientoController extends Controller
{
    public function recruit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reclutable_id' => 'required|exists:reclutables,id',
        ]);

        $reclutable = Reclutable::findOrFail($data['reclutable_id']);

        // Create the recruited pokemon
        Reclutado::create([
            'pokemon_id' => $reclutable->pokemon_id,
            'nombre' => null, // default: uses pokemon name in UI
            'exp' => null,
            'es_shiny' => false,
            'obj_equipados' => null,
            'movimientos' => null,
        ]);

        if ($reclutable->cantidad > 1) {
            $reclutable->decrement('cantidad');
        } else {
            $reclutable->delete();
        }

        return response()->json(['success' => true]);
    }

    public function discardAll(): JsonResponse
    {
        $reclutables = Reclutable::with('pokemon.evolutionChain.pokemon')->get();

        $candyRewards = [];
        foreach ($reclutables as $reclutable) {
            $pokemon = $reclutable->pokemon;
            if (! $pokemon || ! $pokemon->evolution_chain_id) {
                continue;
            }
            $chain = $pokemon->evolutionChain;
            $phase = $chain?->pokemon
                ->where('species_id', '<=', $pokemon->species_id)
                ->count() ?? 1;

            $chainId = $pokemon->evolution_chain_id;
            $candyRewards[$chainId] = ($candyRewards[$chainId] ?? 0) + ($reclutable->cantidad * $phase);
        }

        foreach ($candyRewards as $chainId => $amount) {
            $existing = Caramelo::where('evolution_chain_id', $chainId)->first();
            if ($existing) {
                $existing->increment('cantidad', $amount);
            } else {
                Caramelo::create([
                    'evolution_chain_id' => $chainId,
                    'cantidad' => $amount,
                ]);
            }
        }

        Reclutable::query()->delete();

        return response()->json(['success' => true, 'candies' => $candyRewards]);
    }
}
