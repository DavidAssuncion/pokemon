<?php

namespace App\Http\Controllers;

use Src\Reclutamiento\App\ObtenerPokemonsReclutados;
use Src\Equipos\App\ObtenerEquipos;


class ReclutadosController extends Controller
{
    public function __construct(
        public readonly ObtenerPokemonsReclutados $obtenerPokemonsReclutados,
        public readonly ObtenerEquipos $obtenerEquipos,
    ) {}
    
    public function index()
    {

        return view('reclutados.index', [
            'reclutados' => $this->obtenerPokemonsReclutados->run(),
            'teams' => $this->obtenerEquipos->run(),
        ]);
    }
}
