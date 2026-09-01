<?php

declare(strict_types=1);

use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\ReclutadoController;
use App\Http\Controllers\ReclutamientoController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

// Player section routes
Route::get('/pokedex', [PlayerController::class, 'pokedex']);
Route::get('/reclutamiento', [PlayerController::class, 'reclutamiento']);
Route::get('/equipos', [PlayerController::class, 'equipos']);

// Onboarding: equipo inicial (redirect desde register)
Route::get('/onboarding/equipo-inicial', [OnboardingController::class, 'show'])->name('onboarding.equipo-inicial');
Route::post('/onboarding/equipo-inicial', [OnboardingController::class, 'store']);

// Reclutamiento queue actions (used by reclutamiento view)
Route::post('/reclutamiento/recruit', [ReclutamientoController::class, 'recruit']);
Route::post('/reclutamiento/discard', [ReclutamientoController::class, 'discard']);
Route::post('/reclutamiento/discard-all', [ReclutamientoController::class, 'discardAll']);

// Redirect old /reclutados to /equipos
Route::get('/reclutados', fn () => redirect('/equipos'));

// Team CRUD (moved from reclutados.php — used by equipos view)
Route::post('/teams', [TeamController::class, 'store']);
Route::put('/teams/{team}', [TeamController::class, 'update']);
Route::delete('/teams/{team}', [TeamController::class, 'destroy']);
Route::post('/teams/add-member', [TeamController::class, 'addMember']);
Route::post('/teams/remove-member', [TeamController::class, 'removeMember']);

// Update member role — contrato frontend: POST /teams/update-member-role con member_id + behavior
Route::post('/teams/update-member-role', [TeamController::class, 'updateMemberRoleViaPost']);

// Update member role (PATCH RESTful, compatible hacia atrás)
Route::patch('/teams/member/{member}/role', [TeamController::class, 'updateMemberRole']);

// Reclutado detail: type candy feeding + evolution + release
Route::get('/reclutado/{reclutado}', [ReclutadoController::class, 'show']);
Route::post('/reclutado/{reclutado}/dar-caramelo', [ReclutadoController::class, 'darCaramelo']);
Route::post('/reclutado/{reclutado}/evolucionar', [ReclutadoController::class, 'evolucionar']);
Route::delete('/reclutado/{reclutado}', [ReclutadoController::class, 'destroy']);

// Opciones de evolución bajo demanda (usado por el modal de /equipos)
Route::get('/reclutado/{reclutado}/evoluciones', [ReclutadoController::class, 'evoluciones']);
