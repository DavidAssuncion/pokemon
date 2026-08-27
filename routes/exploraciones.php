<?php

declare(strict_types=1);

use App\Http\Controllers\ExploracionActivaController;
use Illuminate\Support\Facades\Route;

Route::post('/exploraciones', [ExploracionActivaController::class, 'store']);
