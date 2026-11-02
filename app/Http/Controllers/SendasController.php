<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Move;
use App\Models\PlayerInventory;
use App\Models\PlayerMoveUnlock;
use App\Models\PlayerTypePath;
use App\Support\ArbolSendas;
use App\Support\ItemCatalogo;
use App\Support\SendaProgreso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Sendas de tipo: progreso de cuenta y desbloqueo global de movimientos.
 */
class SendasController extends Controller
{
    public function index(): View
    {
        $userId = (int) auth()->id();
        $tipos = ArbolSendas::resumen($userId);

        return view('sendas.index', [
            'tipos' => $tipos,
            'totalDesbloqueados' => array_sum(array_column($tipos, 'desbloqueados')),
            'totalNodos' => array_sum(array_column($tipos, 'total_nodos')),
        ]);
    }

    public function show(string $tipo): View
    {
        $tipoEnum = ArbolSendas::tipoPorSlug($tipo);
        abort_if($tipoEnum === null, 404);

        $userId = (int) auth()->id();

        return view('sendas.show', [
            'arbol' => ArbolSendas::para($userId, $tipoEnum),
        ]);
    }

    public function desbloquear(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:100'],
            'tipo' => ['required', 'string', 'max:30'],
        ]);

        $tipo = ArbolSendas::tipoPorSlug((string) $data['tipo']);
        if ($tipo === null) {
            return response()->json(['error' => 'Tipo de Senda desconocido'], 422);
        }

        $move = Move::query()
            ->where('slug', (string) $data['slug'])
            ->where('type_id', $tipo->value)
            ->first();

        if ($move === null) {
            return response()->json(['error' => 'Ese movimiento no pertenece a esta Senda'], 422);
        }

        $userId = (int) auth()->id();
        $nodo = ArbolSendas::nodo((string) $move->slug, $userId);

        if ($nodo === null) {
            return response()->json(['error' => 'Nodo de Senda no encontrado'], 422);
        }

        if ($nodo['desbloqueado']) {
            return response()->json(['error' => 'Ese movimiento ya está desbloqueado'], 422);
        }

        if (! $nodo['disponible']) {
            return response()->json([
                'error' => "Necesitas nivel {$nodo['nivel_requerido']} de la Senda de {$tipo->label()} para desbloquear este movimiento",
            ], 422);
        }

        $itemKey = ItemCatalogo::keyTipo($tipo->label());
        $coste = (int) $nodo['coste'];

        $resultado = DB::transaction(function () use ($userId, $move, $itemKey, $coste): ?PlayerInventory {
            $caramelo = PlayerInventory::query()
                ->withoutGlobalScope(PlayerInventory::USER_SCOPE_NAME)
                ->where('user_id', $userId)
                ->where('item_key', $itemKey)
                ->lockForUpdate()
                ->first();

            if ($caramelo === null || $caramelo->cantidad < $coste) {
                return null;
            }

            $desbloqueo = PlayerMoveUnlock::query()
                ->withoutGlobalScope(PlayerMoveUnlock::USER_SCOPE_NAME)
                ->firstOrCreate([
                    'user_id' => $userId,
                    'move_id' => $move->id,
                ]);

            if (! $desbloqueo->wasRecentlyCreated) {
                return null;
            }

            $caramelo->decrement('cantidad', $coste);

            return $caramelo->refresh();
        });

        if ($resultado === null) {
            return response()->json([
                'error' => "No tienes suficientes caramelos de {$tipo->label()} o el movimiento ya estaba desbloqueado",
            ], 422);
        }

        return response()->json([
            'success' => true,
            'caramelos' => (int) $resultado->cantidad,
            'nodo' => ArbolSendas::nodo((string) $move->slug, $userId),
            'arbol' => ArbolSendas::para($userId, $tipo),
        ]);
    }

    public function experiencia(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tipo' => ['required', 'string', 'max:30'],
            'cantidad' => ['required', 'integer', 'in:1,10,100'],
        ]);

        $tipo = ArbolSendas::tipoPorSlug((string) $data['tipo']);
        if ($tipo === null) {
            return response()->json(['error' => 'Tipo de Senda desconocido'], 422);
        }

        $userId = (int) auth()->id();
        $cantidad = (int) $data['cantidad'];
        $itemKey = ItemCatalogo::keyTipo($tipo->label());
        $expMaxima = SendaProgreso::expMaxima();
        $expPorCaramelo = SendaProgreso::expPorCaramelo();

        $resultado = DB::transaction(function () use ($userId, $cantidad, $itemKey, $tipo, $expMaxima, $expPorCaramelo): array {
            $path = PlayerTypePath::query()
                ->withoutGlobalScope(PlayerTypePath::USER_SCOPE_NAME)
                ->firstOrCreate(
                    ['user_id' => $userId, 'type_id' => $tipo->value],
                    ['exp' => 0, 'level' => 1],
                );

            $path = PlayerTypePath::query()
                ->withoutGlobalScope(PlayerTypePath::USER_SCOPE_NAME)
                ->whereKey($path->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $caramelo = PlayerInventory::query()
                ->withoutGlobalScope(PlayerInventory::USER_SCOPE_NAME)
                ->where('user_id', $userId)
                ->where('item_key', $itemKey)
                ->lockForUpdate()
                ->first();

            if ($caramelo === null || $caramelo->cantidad <= 0) {
                return ['error' => "No tienes caramelos de {$tipo->label()}"];
            }

            $consumidos = min($cantidad, (int) $caramelo->cantidad);

            $expActual = (int) $path->exp;
            $restante = max(0, $expMaxima - $expActual);
            $maxPorTope = intdiv($restante, $expPorCaramelo);
            $consumidos = min($consumidos, $maxPorTope);

            if ($consumidos <= 0) {
                return [
                    'error' => "La Senda de {$tipo->label()} ha alcanzado el tope de {$expMaxima} EXP. Te quedan {$restante} EXP para el máximo",
                ];
            }

            $path->increment('exp', $expPorCaramelo * $consumidos);
            $path->refresh();
            $path->update(['level' => SendaProgreso::nivel((int) $path->exp)]);

            $caramelo->decrement('cantidad', $consumidos);

            return [
                'caramelos' => (int) $caramelo->cantidad,
                'consumidos' => $consumidos,
                'exp_ganada' => $expPorCaramelo * $consumidos,
            ];
        });

        if (isset($resultado['error'])) {
            return response()->json(['error' => $resultado['error']], 422);
        }

        return response()->json([
            'success' => true,
            'caramelos' => $resultado['caramelos'],
            'cantidad_consumida' => $resultado['consumidos'],
            'exp_ganada' => $resultado['exp_ganada'],
            'arbol' => ArbolSendas::para($userId, $tipo),
        ]);
    }
}
