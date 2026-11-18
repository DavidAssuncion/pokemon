<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\UI\SpritesEntrenador;
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
        $teams = $this->obtenerEquipos->run()->all();

        return view('gimnasios.show', [
            'slug' => $slug,
            'teams' => $teams,
            'sprites' => SpritesEntrenador::manifiesto(),
        ]);
    }

    public function admin(): View
    {
        return view('gimnasios.admin');
    }
}
