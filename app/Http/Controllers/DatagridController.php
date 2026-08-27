<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Datagrid\DatagridService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DatagridController extends Controller
{
    public function __construct(
        private readonly DatagridService $datagrid
    ) {
    }

    public function index(Request $request, string $model): JsonResponse
    {
        $this->requireRegistered($model);

        return response()->json($this->datagrid->list($model, $request->query()));
    }

    public function show(Request $request, string $model, int $id): JsonResponse
    {
        $this->requireRegistered($model);

        $detail = $this->datagrid->detail($model, $id);

        if ($detail === null) {
            abort(404);
        }

        return response()->json($detail);
    }

    private function requireRegistered(string $model): void
    {
        if (! $this->datagrid->registered($model)) {
            abort(404);
        }
    }
}
