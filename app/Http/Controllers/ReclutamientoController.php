<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Reclutable;
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

        if ($reclutable->cantidad > 1) {
            $reclutable->decrement('cantidad');
        } else {
            $reclutable->delete();
        }

        return response()->json(['success' => true]);
    }

    public function discardAll(): JsonResponse
    {
        Reclutable::query()->delete();

        return response()->json(['success' => true]);
    }
}
