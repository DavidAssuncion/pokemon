<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;
use Src\Equipos\App\ObtenerEquipos;
use Src\Habitats\App\ObtenerHabitatDetalle;
use Src\Habitats\App\ObtenerHabitatsPorProvincia;
use Src\Habitats\App\ObtenerPokemonsPorHabitat;

class HabitatsController extends Controller
{
    public function __construct(
        public readonly ObtenerEquipos $obtenerEquipos,
        public readonly ObtenerHabitatsPorProvincia $obtenerHabitatsPorProvincia,
        public readonly ObtenerHabitatDetalle $obtenerHabitatDetalle,
        public readonly ObtenerPokemonsPorHabitat $obtenerPokemonsPorHabitat,
    ) {
    }

    public function index(): View
    {
        $provincias = $this->obtenerHabitatsPorProvincia->handle()->toArray();

        return view('habitats.index', ['provincias' => $provincias]);
    }

    public function show(int $id): View
    {
        return view('habitats.show', [
            'habitat' => $this->obtenerHabitatDetalle->handle($id)->toArray(),
            'teams' => $this->obtenerEquipos->run(),
        ]);
    }

    public function pokemon(int $habitat): \Illuminate\Http\JsonResponse
    {
        $pokemon = $this->obtenerPokemonsPorHabitat->handle($habitat);

        return response()->json($pokemon);
    }
}
