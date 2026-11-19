<?php

declare(strict_types=1);

use App\Http\Controllers\DebugController;
use App\Http\Controllers\PokedexQaController;
use Illuminate\Support\Facades\Route;

/*
| Rutas de QA (/debug y acciones del modal de Pokédex).
|
| Se cargan desde routes/web.php FUERA del grupo `auth` para que `solo-local`
| evalúe el entorno antes de autenticar: fuera de local/testing → 404 también
| para anónimos. Cada controlador repite la guarda (defensa en profundidad).
*/
Route::middleware(['solo-local', 'auth'])->group(function (): void {
    Route::get('/debug', [DebugController::class, 'index'])->name('qa.debug.index');
    Route::post('/debug/experiencia', [DebugController::class, 'ajustarExperiencia'])->name('qa.debug.experiencia');
    Route::post('/debug/inventario', [DebugController::class, 'ajustarInventario'])->name('qa.debug.inventario');

    Route::post('/pokedex/qa/reclutar', [PokedexQaController::class, 'reclutar'])->name('qa.pokedex.reclutar');
    Route::post('/pokedex/qa/caramelo-familia', [PokedexQaController::class, 'carameloFamilia'])->name('qa.pokedex.caramelo-familia');
});
