<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReclutadosController;
use App\Http\Controllers\TeamController;

Route::get('/reclutados', [ReclutadosController::class, 'index']);
Route::post('/teams', [TeamController::class, 'store']);
Route::delete('/teams/{team}', [TeamController::class, 'destroy']);
Route::post('/teams/add-member', [TeamController::class, 'addMember']);
Route::post('/teams/remove-member', [TeamController::class, 'removeMember']);
