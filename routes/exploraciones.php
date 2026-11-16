<?php

declare(strict_types=1);

use App\Http\Controllers\ExploracionActivaController;
use Illuminate\Support\Facades\Route;
use Src\Exploraciones\Infra\Controllers\ExpedicionesPanelController;

// Lecturas: la página procesa pendientes; el resto son solo lectura.
Route::get('/exploraciones', [ExploracionActivaController::class, 'index']);
Route::get('/exploraciones/preview', [ExploracionActivaController::class, 'preview']);
Route::get('/exploraciones/panel/contenido', [ExpedicionesPanelController::class, 'contenido']);
Route::get('/api/exploraciones', [ExpedicionesPanelController::class, 'index']);

Route::post('/exploraciones', [ExploracionActivaController::class, 'store']);

// Panel: detalle y archivado de una expedición concreta (id por URL).
Route::get('/exploraciones/{exploracion}/panel/detalle', [ExpedicionesPanelController::class, 'detalle'])
    ->whereNumber('exploracion');
Route::post('/exploraciones/{exploracion}/archivar', [ExpedicionesPanelController::class, 'archivar'])
    ->whereNumber('exploracion');

Route::post('/exploraciones/{exploracion}/recoger', [ExploracionActivaController::class, 'recoger'])
    ->whereNumber('exploracion');
Route::post('/exploraciones/{exploracion}/cancelar', [ExploracionActivaController::class, 'cancelar'])
    ->whereNumber('exploracion');
Route::post('/exploraciones/{exploracion}/cerrar', [ExploracionActivaController::class, 'cerrar'])
    ->whereNumber('exploracion');

// Exploración individual (API JSON): el botín de bayas/bonguris se calcula
// al finalizar la expedición.
Route::post('/api/exploraciones/store-individual', [ExploracionActivaController::class, 'storeIndividual']);
