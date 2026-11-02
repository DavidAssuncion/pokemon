<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PlayerInventory;
use App\Models\Reclutable;
use App\Support\CadenasEvolutivas;
use App\Support\CrearReclutado;
use App\Support\ItemCatalogo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Src\Granjas\App\ResolverBonosFamilia;
use Src\Pokemon\Domain\Stats\GeneradorIvs;
use Src\Reclutamiento\Domain\DataTransferObjects\DatosNuevoReclutado;

class ReclutamientoController extends Controller
{
    public function __construct(
        private readonly CrearReclutado $crearReclutado,
        private readonly ResolverBonosFamilia $bonosFamilia,
    ) {
    }

    public function recruit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reclutable_id' => 'required|exists:reclutables,id',
        ]);

        return DB::transaction(function () use ($data): JsonResponse {
            // Propiedad: el global scope BelongsToUser + findOrFail → 404 si el
            // reclutable es de otro jugador (la regla exists no filtra por usuario).
            $reclutable = Reclutable::with('pokemon')->findOrFail($data['reclutable_id']);

            $userId = (int) auth()->id();
            $evolutionChainId = $reclutable->pokemon->evolution_chain_id ?? 0;

            $bonos = $evolutionChainId > 0
                ? $this->bonosFamilia->paraFamilia($userId, $evolutionChainId)
                : null;

            // Sin nido los IVs son 0-15; con nido se garantiza el mínimo de
            // su rango. Los EVs de complemento también se aplican.
            $ivs = GeneradorIvs::reclutamiento($bonos?->nido->nivel ?? 0);
            $evs = $bonos?->evsFamilia;

            $this->crearReclutado->crear(
                new DatosNuevoReclutado(
                    userId: $userId,
                    pokemonId: $reclutable->pokemon_id,
                    ivs: $ivs,
                ),
                $evs,
            );

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

        return DB::transaction(function () use ($data): JsonResponse {
            // Lock de la fila antes de leer `cantidad`: dos descartes
            // concurrentes no pueden premiar la misma unidad.
            $reclutable = Reclutable::with('pokemon')
                ->lockForUpdate()
                ->findOrFail($data['reclutable_id']);

            $cantidad = min((int) $data['cantidad'], $reclutable->cantidad);

            $this->otorgarCaramelos([$reclutable], auth()->id(), $cantidad);

            if ($cantidad >= $reclutable->cantidad) {
                $reclutable->delete();
            } else {
                $reclutable->decrement('cantidad', $cantidad);
            }

            return response()->json(['success' => true]);
        });
    }

    public function discardAll(): JsonResponse
    {
        return DB::transaction(function (): JsonResponse {
            // Global scope BelongsToUser: solo los reclutables del usuario autenticado.
            // Lock de las filas antes de calcular la recompensa: dos descartes
            // concurrentes no pueden premiar las mismas unidades.
            $reclutables = Reclutable::with('pokemon')->lockForUpdate()->get();

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
        $etapasPorCadena = CadenasEvolutivas::etapasDe(
            collect($reclutables)->map(fn (Reclutable $reclutable): ?int => $reclutable->pokemon?->evolution_chain_id)
        );

        foreach ($reclutables as $reclutable) {
            $pokemon = $reclutable->pokemon;
            if (! $pokemon || ! $pokemon->evolution_chain_id) {
                continue;
            }

            $miembros = $miembrosPorCadena[$pokemon->evolution_chain_id] ?? null;
            $phase = $etapasPorCadena[$pokemon->evolution_chain_id][$pokemon->species_id] ?? ($miembros?->where('species_id', '<=', $pokemon->species_id)->count() ?? 1);

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
