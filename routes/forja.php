<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Forja\Infra\Controllers\ForjaController;

// Forja — ver src/Forja/
Route::get('/api/forja/recetas', [ForjaController::class, 'recetas'])->name('forja.recetas');
Route::post('/api/forja/fabricar', [ForjaController::class, 'fabricar'])->name('forja.fabricar');
Route::get('/forja', [ForjaController::class, 'index'])->name('forja.index');
