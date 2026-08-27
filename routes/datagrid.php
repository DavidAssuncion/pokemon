<?php

declare(strict_types=1);

use App\Http\Controllers\DatagridController;
use Illuminate\Support\Facades\Route;

// Datagrid JSON API (whitelisted models only — see DatagridServiceProvider)
Route::get('/datagrid/{model}', [DatagridController::class, 'index']);
Route::get('/datagrid/{model}/{id}/detalle', [DatagridController::class, 'show'])->whereNumber('id');
