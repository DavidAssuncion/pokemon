<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;
use Src\Equipos\App\ObtenerEquipos;

/**
 * Vista del detalle de una facción de hábitat temporal.
 */
class HabitatsTemporalesViewController extends Controller
{
    public function __construct(
        private readonly ObtenerEquipos $obtenerEquipos,
    ) {
    }

    public function show(string $faccion): View
    {
        return view('habitats.temporales.show', [
            'slug' => $faccion,
            'teams' => $this->obtenerEquipos->run()->all(),
        ]);
    }
}
