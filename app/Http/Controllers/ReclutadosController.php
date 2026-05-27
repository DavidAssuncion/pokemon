<?php

namespace App\Http\Controllers;

use App\Models\Reclutado;
use App\Models\Team;

class ReclutadosController extends Controller
{
    public function index()
    {
        $reclutados = Reclutado::with('pokemon')->get();
        $teams = Team::with('members.reclutado.pokemon')->get();

        return view('reclutados.index', [
            'reclutados' => $reclutados,
            'teams' => $teams,
        ]);
    }
}
