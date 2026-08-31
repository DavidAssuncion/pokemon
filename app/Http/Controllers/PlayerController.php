<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Datagrid\DatagridService;
use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\ExploracionActiva;
use App\Models\Reclutable;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\View\View;
use Src\Equipos\App\ObtenerEquipos;
use Src\Exploraciones\Domain\RolExploracion;
use Src\Exploraciones\Domain\SinergiaEquipo;

class PlayerController extends Controller
{
    public function __construct(
        public readonly ObtenerEquipos $obtenerEquipos,
        private readonly DatagridService $datagrid,
    ) {
    }

    public function pokedex(): View
    {
        $page = $this->datagrid->list('pokemon', [
            'per_page' => 120,
            'sort' => 'id',
            'order' => 'asc',
            'filter' => ['visto' => '1'],
        ]);

        return view('pokedex.index', [
            'pokemons' => $page,
            'counts' => $page['meta']['counts'] ?? ['total' => 0, 'vistos' => 0, 'atrapados' => 0, 'no_vistos' => 0],
            'tipos' => TipoEnum::options(),
            'stats' => StatEnum::options(),
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
        $teams = Team::with('members.reclutado.pokemon')->get()->sortBy('id')->values();

        $teams = $teams->map(function (Team $team): array {
            $members = $team->members->sortBy('slot')->values();

            $sinergia = null;
            if ($members->count() === 3) {
                $roles = $members->map(
                    fn (TeamMember $m): RolExploracion =>
                    RolExploracion::tryFrom($m->behavior) ?? RolExploracion::COMBATIENTE
                )->all();
                $sinergia = SinergiaEquipo::sinergiaPara($roles);
            }

            return [
                'id' => $team->id,
                'name' => $team->name,
                'members' => $members->map(function (TeamMember $m): array {
                    return [
                        'id' => $m->id,
                        'team_id' => $m->team_id,
                        'pokemon_id' => $m->pokemon_id,
                        'slot' => $m->slot,
                        'behavior' => $m->behavior,
                        'reclutado' => $m->reclutado,
                    ];
                })->all(),
                'sinergia' => $sinergia,
                'sinergia_nombre' => $sinergia['nombre'] ?? null,
            ];
        })->all();

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
