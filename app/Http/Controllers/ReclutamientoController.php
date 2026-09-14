<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ActualizarPokedexJob;
use App\Models\PlayerInventory;
use App\Models\Reclutable;
use App\Models\Reclutado;
use App\Support\CadenasEvolutivas;
use App\Support\ItemCatalogo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReclutamientoController extends Controller
{
    public function recruit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reclutable_id' => 'required|exists:reclutables,id',
        ]);

        return DB::transaction(function () use ($data): JsonResponse {
            // Propiedad: el global scope BelongsToUser + findOrFail → 404 si el
            // reclutable es de otro jugador (la regla exists no filtra por usuario).
            $reclutable = Reclutable::findOrFail($data['reclutable_id']);

            // Create the recruited pokemon
            Reclutado::create([
                'user_id' => auth()->id(),
                'pokemon_id' => $reclutable->pokemon_id,
                'nombre' => null, // default: uses pokemon name in UI
                'exp' => [
                    'total' => 1500,
                    'tipos' => [],
                ],
                'es_shiny' => false,
                'obj_equipados' => null,
                'movimientos' => null,
            ]);

            ActualizarPokedexJob::dispatch(auth()->id(), $reclutable->pokemon_id, 'RECLUTADO');

            if ($reclutable->cantidad > 1) {
                $reclutable->decrement('cantidad');
            } else {
                $reclutable->delete();
            }

            return response()->json(['success' => true]);
        });
    }

    public function discard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reclutable_id' => 'required|exists:reclutables,id',
            'cantidad' => 'required|integer|min:1',
        ]);

        $reclutable = Reclutable::with('pokemon')
            ->findOrFail($data['reclutable_id']);

        $cantidad = min((int) $data['cantidad'], $reclutable->cantidad);

        $this->otorgarCaramelos([$reclutable], auth()->id(), $cantidad);

        if ($cantidad >= $reclutable->cantidad) {
            $reclutable->delete();
        } else {
            $reclutable->decrement('cantidad', $cantidad);
        }

        return response()->json(['success' => true]);
    }

    public function discardAll(): JsonResponse
    {
        return DB::transaction(function (): JsonResponse {
            // Global scope BelongsToUser: solo los reclutables del usuario autenticado.
            $reclutables = Reclutable::with('pokemon')->get();

            $candyRewards = $this->otorgarCaramelos($reclutables->all(), auth()->id());

            Reclutable::query()->delete();

            return response()->json(['success' => true, 'candies' => $candyRewards]);
        });
    }

    /**
     * Award candies for the discarded pokemon: evolution phase × discarded amount,
     * to the authenticated player's inventory (player_inventory, item_key familia:{chain}).
     *
     * @param  array<int, Reclutable>  $reclutables
     * @return array<int, int>
     */
    private function otorgarCaramelos(array $reclutables, ?int $userId, ?int $cantidad = null): array
    {
        $candyRewards = [];
        $miembrosPorCadena = CadenasEvolutivas::miembrosDe(
            collect($reclutables)->map(fn (Reclutable $reclutable): ?int => $reclutable->pokemon?->evolution_chain_id)
        );

        foreach ($reclutables as $reclutable) {
            $pokemon = $reclutable->pokemon;
            if (! $pokemon || ! $pokemon->evolution_chain_id) {
                continue;
            }

            $miembros = $miembrosPorCadena[$pokemon->evolution_chain_id] ?? null;
            $phase = $miembros?->where('species_id', '<=', $pokemon->species_id)->count() ?? 1;

            $descartados = $cantidad ?? $reclutable->cantidad;
            $chainId = $pokemon->evolution_chain_id;
            $candyRewards[$chainId] = ($candyRewards[$chainId] ?? 0) + ($descartados * $phase);
        }

        foreach ($candyRewards as $chainId => $amount) {
            PlayerInventory::firstOrCreate(
                ['user_id' => $userId ?? 1, 'item_key' => ItemCatalogo::keyFamilia($chainId)],
                ['cantidad' => 0],
            )->increment('cantidad', $amount);
        }

        return $candyRewards;
    }
}
