<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ActualizarPokedexJob;
use App\Models\PlayerInventory;
use App\Models\Reclutado;
use App\Support\ItemCatalogo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Src\Reclutamiento\App\ServicioEvolucion;
use Src\Shared\Domain\NivelHelper;

class ReclutadoController extends Controller
{
    public function show(Reclutado $reclutado): View
    {
        $reclutado->load('pokemon.types');

        $siguiente = ServicioEvolucion::siguienteEvolucion($reclutado->pokemon);
        $expTotal = $reclutado->exp->total();

        return view('reclutado.show', [
            'reclutado' => [
                'id' => $reclutado->id,
                'nombre' => $reclutado->nombre,
                'pokemon_id' => $reclutado->pokemon_id,
                'pokemon_nombre' => $reclutado->pokemon?->name,
                'nivel' => NivelHelper::nivelDesdeExperiencia($expTotal),
                'exp_total' => $expTotal,
                'imagen' => "/images/iconos/{$reclutado->pokemon_id}.png",
            ],
            'siguiente' => $siguiente !== null
                ? [
                    'pokemon_id' => $siguiente->id,
                    'nombre' => $siguiente->name,
                    'imagen' => "/images/iconos/{$siguiente->id}.png",
                ]
                : null,
            'requisitos' => ServicioEvolucion::requisitos($reclutado, auth()->id()),
            'puedeEvolucionar' => ServicioEvolucion::puedeEvolucionar($reclutado, auth()->id()),
        ]);
    }

    public function darCaramelo(Request $request, Reclutado $reclutado): JsonResponse
    {
        $data = $request->validate([
            'tipo' => 'required|string',
        ]);

        $tipo = $data['tipo'];
        $siguiente = ServicioEvolucion::siguienteEvolucion($reclutado->pokemon);
        $tiposRequeridos = ServicioEvolucion::tiposRequeridos($siguiente);

        if (! in_array($tipo, $tiposRequeridos, true)) {
            return response()->json(['error' => 'Ese tipo no es necesario para la evolución'], 422);
        }

        $userId = auth()->id();

        $caramelo = DB::transaction(function () use ($tipo, $userId): ?PlayerInventory {
            // Lock de la fila del inventario del jugador: evita doble consumo
            // concurrente (decrement atómico tras el chequeo de stock).
            $caramelo = PlayerInventory::where('user_id', $userId)
                ->where('item_key', ItemCatalogo::keyTipo($tipo))
                ->lockForUpdate()
                ->first();

            if ($caramelo === null || $caramelo->cantidad <= 0) {
                return null;
            }

            $caramelo->decrement('cantidad');

            return $caramelo;
        });

        if ($caramelo === null) {
            return response()->json(['error' => "No hay caramelos de tipo {$tipo}"], 422);
        }

        // Exp de tipo al JSON del reclutado (cast inmutable: se reasigna).
        $reclutado->exp = $reclutado->exp->sumarExpTipo($tipo, 100);
        $reclutado->save();

        return response()->json([
            'success' => true,
            'actual' => $reclutado->exp->expTipo($tipo),
            'caramelos_disponibles' => $caramelo->cantidad,
            'puede_evolucionar' => ServicioEvolucion::puedeEvolucionar($reclutado, $userId),
        ]);
    }

    public function evolucionar(Reclutado $reclutado): JsonResponse
    {
        $userId = auth()->id();
        $siguiente = ServicioEvolucion::siguienteEvolucion($reclutado->pokemon);

        if ($siguiente === null || ! ServicioEvolucion::puedeEvolucionar($reclutado, $userId)) {
            return response()->json(['error' => 'No cumple los requisitos'], 422);
        }

        $umbral = ServicioEvolucion::umbralParaNivel(ServicioEvolucion::nivelDe($reclutado));
        $tipos = ServicioEvolucion::tiposRequeridos($siguiente);

        DB::transaction(function () use ($reclutado, $siguiente, $tipos, $umbral): void {
            ServicioEvolucion::consumirExpTipo($reclutado, $tipos, $umbral);
            $reclutado->update(['pokemon_id' => $siguiente->id]);
        });

        ActualizarPokedexJob::dispatch($userId, $siguiente->id, 'RECLUTADO');

        return response()->json(['success' => true, 'pokemon_id' => $siguiente->id]);
    }
}
