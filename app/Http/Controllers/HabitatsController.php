<?php

namespace App\Http\Controllers;

use Src\Equipos\App\ObtenerEquipos;
use Src\Habitats\App\ObtenerHabitatDetalle;
use Src\Habitats\Infra\HabitatRepository;


class HabitatsController extends Controller
{
    public function __construct(
        public readonly ObtenerEquipos $obtenerEquipos,
        public readonly HabitatRepository $habitatRepository,

    ) {}

    public function show(int $id)
    {
        $useCase1 = new ObtenerHabitatDetalle($this->habitatRepository);

        return view('habitats.show', [
            'habitat' => $useCase1->handle($id),
            'teams' => $this->obtenerEquipos->run(),
        ]);
    }
}
