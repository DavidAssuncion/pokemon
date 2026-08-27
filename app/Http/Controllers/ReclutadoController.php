<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ActualizarPokedexJob;
use App\Models\CarameloTipo;
use App\Models\Reclutado;
use App\Models\ReclutadoExpTipo;
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
        $reclutado->load('pokemon.types', 'expTipos');

        $siguiente = ServicioEvolucion::siguienteEvolucion($reclutado->pokemon);
        $expTotal = (int) ($reclutado->exp['total'] ?? 0);

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
            'requisitos' => ServicioEvolucion::requisitos($reclutado),
            'puedeEvolucionar' => ServicioEvolucion::puedeEvolucionar($reclutado),
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

        $caramelo = CarameloTipo::where('tipo', $tipo)->first();
        if ($caramelo === null || $caramelo->cantidad <= 0) {
            return response()->json(['error' => "No hay caramelos de tipo {$tipo}"], 422);
        }

        $caramelo->decrement('cantidad');

        $expTipo = ReclutadoExpTipo::firstOrCreate(
            ['reclutado_id' => $reclutado->id, 'tipo' => $tipo],
            ['cantidad' => 0]
        );
        $expTipo->increment('cantidad', 100);

        return response()->json([
            'success' => true,
            'actual' => $expTipo->cantidad,
            'caramelos_disponibles' => $caramelo->cantidad,
            'puede_evolucionar' => ServicioEvolucion::puedeEvolucionar($reclutado->fresh()),
        ]);
    }

    public function evolucionar(Reclutado $reclutado): JsonResponse
    {
        $siguiente = ServicioEvolucion::siguienteEvolucion($reclutado->pokemon);

        if ($siguiente === null || ! ServicioEvolucion::puedeEvolucionar($reclutado)) {
            return response()->json(['error' => 'No cumple los requisitos'], 422);
        }

        $umbral = ServicioEvolucion::umbralParaNivel(ServicioEvolucion::nivelDe($reclutado));
        $tipos = ServicioEvolucion::tiposRequeridos($siguiente);

        DB::transaction(function () use ($reclutado, $siguiente, $tipos, $umbral): void {
            ServicioEvolucion::consumirExpTipo($reclutado, $tipos, $umbral);
            $reclutado->update(['pokemon_id' => $siguiente->id]);
        });

        ActualizarPokedexJob::dispatch($siguiente->id, 'RECLUTADO');

        return response()->json(['success' => true, 'pokemon_id' => $siguiente->id]);
    }
}
