<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Move;
use App\Models\PlayerInventory;
use App\Models\PlayerMoveUnlock;
use App\Support\ArbolSendas;
use App\Support\ItemCatalogo;
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
        $nivelCuenta = (int) (auth()->user()?->nivel() ?? 1);

        return view('sendas.index', [
            'tipos' => ArbolSendas::resumen($userId, $nivelCuenta),
        ]);
    }

    public function show(string $tipo): View
    {
        $tipoEnum = ArbolSendas::tipoPorSlug($tipo);
        abort_if($tipoEnum === null, 404);

        $userId = (int) auth()->id();
        $nivelCuenta = (int) (auth()->user()?->nivel() ?? 1);

        return view('sendas.show', [
            'arbol' => ArbolSendas::para($userId, $nivelCuenta, $tipoEnum),
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
        $nivelCuenta = (int) (auth()->user()?->nivel() ?? 1);
        $nodo = ArbolSendas::nodo((string) $move->slug, $userId, $nivelCuenta);

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
            'nodo' => ArbolSendas::nodo((string) $move->slug, $userId, $nivelCuenta),
            'arbol' => ArbolSendas::para($userId, $nivelCuenta, $tipo),
        ]);
    }
}
