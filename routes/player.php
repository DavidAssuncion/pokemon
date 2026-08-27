<?php

declare(strict_types=1);

use App\Http\Controllers\PlayerController;
use App\Http\Controllers\ReclutamientoController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

// Player section routes
Route::get('/pokedex', [PlayerController::class, 'pokedex']);
Route::get('/reclutamiento', [PlayerController::class, 'reclutamiento']);
Route::get('/equipos', [PlayerController::class, 'equipos']);

// Reclutamiento queue actions (used by reclutamiento view)
Route::post('/reclutamiento/recruit', [ReclutamientoController::class, 'recruit']);
Route::post('/reclutamiento/discard-all', [ReclutamientoController::class, 'discardAll']);

// Redirect old /reclutados to /equipos
Route::get('/reclutados', fn () => redirect('/equipos'));

// Team CRUD (moved from reclutados.php — used by equipos view)
Route::post('/teams', [TeamController::class, 'store']);
Route::put('/teams/{team}', [TeamController::class, 'update']);
Route::delete('/teams/{team}', [TeamController::class, 'destroy']);
Route::post('/teams/add-member', [TeamController::class, 'addMember']);
Route::post('/teams/remove-member', [TeamController::class, 'removeMember']);
