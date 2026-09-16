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
use App\Support\ReclutadoSerializer;
use Illuminate\Support\Facades\Log;
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
            if ($members->count() >= 3) {
                // RFC: la sinergia del equipo se calcula con los roles
                // INDIVIDUALES de los reclutados miembro (reclutado->rol()),
                // no con team_members.behavior.
                // Equipos de 4-5: la tabla solo cubre pares/tríos → null
                // (no mostrar sinergia), aceptable.
                $roles = $members->map(
                    fn (TeamMember $m): RolExploracion => $m->reclutado?->rol() ?? RolExploracion::COMBATIENTE
                )->all();
                $sinergia = SinergiaEquipo::sinergiaPara($roles);
            }

            return [
                'id' => $team->id,
                'name' => $team->name,
                'formacion' => $team->formacion ?? [],
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

        $reclutadosQuery = Reclutado::with(['pokemon.types', 'pokemon.stats', 'teamMember'])->get();

        Log::info('[equipos:debug] user', [
            'user_id' => auth()->id(),
            'experiencia' => auth()->user()?->experiencia,
            'nivel' => auth()->user()?->nivel(),
            'reclutados_count' => $reclutadosQuery->count(),
            'reclutado_ids' => $reclutadosQuery->pluck('id')->all(),
            'user_id_column' => auth()->user()?->getAttributeValue('id'),
        ]);

        $teamIds = $reclutadosQuery->filter(
            fn (Reclutado $r): bool => $r->teamMember !== null
        )->pluck('id')->toArray();

        $reclutados = $reclutadosQuery
            ->map(function (Reclutado $reclutado): array {
                try {
                    return ReclutadoSerializer::serializar($reclutado, auth()->user()?->nivel());
                } catch (\Throwable $e) {
                    Log::error('[equipos:debug] fallo serializando reclutado', [
                        'reclutado_id' => $reclutado->id,
                        'pokemon_id' => $reclutado->pokemon_id,
                        'user_id' => $reclutado->user_id,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            })
            ->values();

        Log::info('[equipos:debug] serializado', [
            'reclutados_serializados' => count($reclutados),
            'primer_reclutado' => $reclutados[0] ?? null,
            'teamIds_count' => count($teamIds),
        ]);

        $reclutadosEnExploracion = ExploracionActiva::whereNull('regreso')
            ->pluck('reclutado_id')
            ->toArray();

        $equiposEnExploracion = $reclutadosEnExploracion !== []
            ? \App\Models\TeamMember::whereIn('pokemon_id', $reclutadosEnExploracion)
                ->pluck('team_id')
                ->unique()
                ->values()
                ->toArray()
            : [];

        $pokemonCheck = \App\Models\Pokemon::with('stats')->find(436);
        Log::info('[equipos:debug] pokemon 436', [
            'existe' => $pokemonCheck !== null,
            'stats_count' => $pokemonCheck?->stats->count(),
        ]);

        return view('equipos.index', [
            'teams' => $teams,
            'reclutados' => $reclutados,
            'teamIds' => $teamIds,
            'equiposEnExploracion' => $equiposEnExploracion,
            'reclutadosEnExploracion' => $reclutadosEnExploracion,
        ]);
    }
}
