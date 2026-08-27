<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ExploracionActiva;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Src\Habitats\App\ValidadorExploracion;

class ExploracionActivaController extends Controller
{
    public function __construct(
        private readonly ValidadorExploracion $validadorExploracion,
    ) {
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'team_id' => 'required|exists:teams,id',
            'habitat_id' => 'required|exists:habitats,id',
            'level' => 'required|integer|min:1|max:3',
            'duration_hours' => 'nullable|integer|min:1|max:72',
            'return_time' => 'nullable|date_format:H:i',
            'indefinido' => 'nullable|boolean',
        ]);

        // Check team is not already in exploration
        if (! $this->validadorExploracion->equipoDisponible((int) $data['team_id'])) {
            return redirect()->back()->with('error', 'El equipo ya está en una exploración activa.');
        }

        $indefinido = ($data['indefinido'] ?? false) || (! isset($data['duration_hours']) && ! isset($data['return_time']));

        $horaLimite = null;
        if (isset($data['return_time'])) {
            $horaLimite = Carbon::today()->setTimeFromTimeString($data['return_time']);
        }

        ExploracionActiva::create([
            'equipo_id' => $data['team_id'],
            'habitat_id' => $data['habitat_id'],
            'nivel' => $data['level'],
            'duracion_horas' => $data['duration_hours'] ?? null,
            'hora_limite' => $horaLimite,
            'indefinido' => $indefinido,
            'inicio_exploracion' => null,
            'llegada_destino' => null,
            'regreso' => null,
        ]);

        return redirect()->back()->with('success', 'Exploración iniciada correctamente.');
    }
}
