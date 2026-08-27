<?php

declare(strict_types=1);

use App\Http\Controllers\ReclutadosController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/reclutados', [ReclutadosController::class, 'index']);
Route::post('/teams', [TeamController::class, 'store']);
Route::put('/teams/{team}', [TeamController::class, 'update']);
Route::delete('/teams/{team}', [TeamController::class, 'destroy']);
Route::post('/teams/add-member', [TeamController::class, 'addMember']);
Route::post('/teams/remove-member', [TeamController::class, 'removeMember']);
