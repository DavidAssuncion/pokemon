<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ExploracionActiva;
use App\Models\Pokedex;
use App\Models\Pokemon;
use App\Models\Reclutable;
use App\Models\Reclutado;
use App\Models\Team;
use Illuminate\View\View;
use Src\Equipos\App\ObtenerEquipos;

class PlayerController extends Controller
{
    public function __construct(
        public readonly ObtenerEquipos $obtenerEquipos,
    ) {
    }

    public function pokedex(): View
    {
        $allPokemon = Pokemon::all();

        $pokedexEntries = Pokedex::all()->keyBy('pokemon_id');

        $pokemons = $allPokemon->map(function ($pokemon) use ($pokedexEntries) {
            $entry = $pokedexEntries->get($pokemon->id);

            $stats = $pokemon->stats()->get()->map(fn ($stat) => [
                'name' => $stat->stat_nombre,
                'value' => $stat->base_stat,
            ])->values()->toArray();

            $types = $pokemon->types()->get()->map(fn ($type) => $type->tipo_nombre)->values()->toArray();

            return [
                'id' => $pokemon->id,
                'name' => $pokemon->name,
                'visto' => $entry ? $entry->visto : false,
                'atrapado' => $entry ? $entry->atrapado : false,
                'stats' => $stats,
                'types' => $types,
            ];
        })->toArray();

        return view('pokedex.index', ['pokemons' => $pokemons]);
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

        $reclutados = Reclutado::with('pokemon')->get();

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
