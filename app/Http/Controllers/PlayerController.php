<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Datagrid\DatagridService;
use App\Enums\TipoEnum;
use App\Models\ExploracionActiva;
use App\Models\Reclutable;
use App\Models\Reclutado;
use App\Models\Team;
use Illuminate\View\View;
use Src\Equipos\App\ObtenerEquipos;

class PlayerController extends Controller
{
    public function __construct(
        public readonly ObtenerEquipos $obtenerEquipos,
        private readonly DatagridService $datagrid,
    ) {
    }

    public function pokedex(): View
    {
        $page = $this->datagrid->list('pokemon', ['per_page' => 100, 'sort' => 'id', 'order' => 'asc']);

        return view('pokedex.index', [
            'pokemons' => $page,
            'counts' => $page['meta']['counts'] ?? ['total' => 0, 'vistos' => 0, 'atrapados' => 0, 'no_vistos' => 0],
            'tipos' => TipoEnum::options(),
        ]);
    }

    public function reclutamiento(): View
    {
        $reclutables = Reclutable::with('pokemon')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn (Reclutable $r) => [
                'id' => $r->id,
                'pokemon_id' => $r->pokemon_id,
                'nombre' => $r->pokemon?->name ?? 'Desconocido',
                'cantidad' => $r->cantidad,
            ])
            ->values()
            ->toArray();

        return view('reclutamiento.index', ['reclutables' => $reclutables]);
    }

    public function equipos(): View
    {
        $teams = Team::with('members.reclutado.pokemon')->get();

        $reclutados = Reclutado::with('pokemon.types')->get();

        $teamIds = $reclutados->filter(function ($r) {
            return $r->teamMember !== null;
        })->pluck('id')->toArray();

        $equiposEnExploracion = ExploracionActiva::whereNull('regreso')
            ->get()
            ->pluck('equipo_id')
            ->toArray();

        return view('equipos.index', [
            'teams' => $teams,
            'reclutados' => $reclutados,
            'teamIds' => $teamIds,
            'equiposEnExploracion' => $equiposEnExploracion,
        ]);
    }
}
