<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ActualizarPokedexJob;
use App\Models\Caramelo;
use App\Models\Pokemon;
use App\Models\Reclutable;
use App\Models\Reclutado;
use Illuminate\Database\Eloquent\Collection;
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

        ActualizarPokedexJob::dispatch($reclutable->pokemon_id, 'RECLUTADO');

        if ($reclutable->cantidad > 1) {
            $reclutable->decrement('cantidad');
        } else {
            $reclutable->delete();
        }

        return response()->json(['success' => true]);
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

        $this->otorgarCaramelos([$reclutable], $cantidad);

        if ($cantidad >= $reclutable->cantidad) {
            $reclutable->delete();
        } else {
            $reclutable->decrement('cantidad', $cantidad);
        }

        return response()->json(['success' => true]);
    }

    public function discardAll(): JsonResponse
    {
        $reclutables = Reclutable::with('pokemon')->get();

        $candyRewards = $this->otorgarCaramelos($reclutables->all());

        Reclutable::query()->delete();

        return response()->json(['success' => true, 'candies' => $candyRewards]);
    }

    /**
     * Award candies for the discarded pokemon: evolution phase × discarded amount.
     *
     * @param  array<int, Reclutable>  $reclutables
     * @return array<int, int>
     */
    private function otorgarCaramelos(array $reclutables, ?int $cantidad = null): array
    {
        $candyRewards = [];
        $miembrosPorCadena = $this->miembrosDeLasCadenas($reclutables);

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

        return $candyRewards;
    }

    /**
     * Miembros de TODAS las familias implicadas (por columna evolution_chain_id),
     * keyed por chain id. Sustituye a la antigua relación de la tabla
     * evolution_chains (eliminada): mismo criterio (misma columna) y además cubre
     * cadenas huérfanas (bug 23503) con fase 1 en vez de error.
     *
     * @param  array<int, Reclutable>  $reclutables
     * @return array<int, Collection<int, Pokemon>>
     */
    private function miembrosDeLasCadenas(array $reclutables): array
    {
        $chainIds = collect($reclutables)
            ->map(fn (Reclutable $reclutable): ?int => $reclutable->pokemon?->evolution_chain_id)
            ->filter()
            ->unique()
            ->values();

        if ($chainIds->isEmpty()) {
            return [];
        }

        $query = Pokemon::query();
        $query->getQuery()->whereIn('evolution_chain_id', $chainIds);

        return $query->get(['id', 'name', 'species_id', 'evolution_chain_id'])
            ->groupBy('evolution_chain_id')
            ->all();
    }
}
