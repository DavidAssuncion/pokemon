<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Src\Equipos\App\CrearEquipoInicial;

class OnboardingController extends Controller
{
    public function show(): RedirectResponse|View
    {
        $tieneEquipo = Team::where('user_id', Auth::id())->exists();

        if ($tieneEquipo) {
            return redirect('/');
        }

        return view('onboarding.equipo-inicial', [
            'equipos' => CrearEquipoInicial::equiposConNombres(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'team_key' => 'required|in:A,B,C',
        ]);

        if (Team::where('user_id', Auth::id())->exists()) {
            return redirect()->back()->with('error', 'Ya tienes un equipo creado');
        }

        CrearEquipoInicial::crear((int) Auth::id(), $data['team_key']);

        return redirect('/')->with('success', 'Equipo inicial creado correctamente');
    }
}
