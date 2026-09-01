<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;
use Src\Equipos\App\ObtenerEquipos;

class GimnasiosViewController extends Controller
{
    public function __construct(
        private readonly ObtenerEquipos $obtenerEquipos,
    ) {
    }

    public function index(): View
    {
        return view('gimnasios.index');
    }

    public function show(string $slug): View
    {
        $teams = $this->obtenerEquipos->run();

        return view('gimnasios.show', [
            'slug' => $slug,
            'teams' => $teams,
        ]);
    }
}
