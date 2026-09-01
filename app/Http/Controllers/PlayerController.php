<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Datagrid\DatagridService;
use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\ExploracionActiva;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use App\Models\Reclutable;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\View\View;
use Src\Equipos\App\ObtenerEquipos;
use Src\Exploraciones\Domain\RolExploracion;
use Src\Exploraciones\Domain\SinergiaEquipo;
use Src\Shared\Domain\NivelHelper;

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

        $reclutadosQuery = Reclutado::with(['pokemon.types', 'pokemon.stats', 'teamMember'])->get();

        $teamIds = $reclutadosQuery->filter(
            fn (Reclutado $r): bool => $r->teamMember !== null
        )->pluck('id')->toArray();

        $reclutados = $reclutadosQuery
            ->map(fn (Reclutado $reclutado): array => $this->serializarReclutado($reclutado))
            ->values();

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

    /**
     * Serializa un reclutado para /equipos preservando el contrato existente
     * (pokemon.types[].tipo_nombre, campos base) y añadiendo nivel, exp_total,
     * base_experience, es_shiny y stats ordenadas por stat (1-6).
     *
     * @return array<string, mixed>
     */
    private function serializarReclutado(Reclutado $reclutado): array
    {
        $datos = $reclutado->toArray();

        $datos['nivel'] = NivelHelper::nivelDesdeExperiencia($reclutado->exp->total());
        $datos['exp_total'] = $reclutado->exp->total();
        $datos['base_experience'] = $reclutado->pokemon?->base_experience;
        $datos['es_shiny'] = $reclutado->es_shiny;
        $datos['stats'] = $this->statsDe($reclutado);

        if ($reclutado->pokemon?->types->isNotEmpty()) {
            $datos['pokemon']['types'] = $this->tiposDe($reclutado);
        }

        return $datos;
    }

    /**
     * Stats base del pokémon como {name, value} con label en español,
     * ordenadas por stat (1-6).
     *
     * @return list<array{name: string, value: int}>
     */
    private function statsDe(Reclutado $reclutado): array
    {
        return $reclutado->pokemon?->stats
            ->sortBy(fn (PokemonStat $stat): int => $stat->stat->value)
            ->map(fn (PokemonStat $stat): array => [
                'name' => $stat->stat->label(),
                'value' => $stat->base_stat,
            ])
            ->values()
            ->all() ?? [];
    }

    /**
     * Tipos del pokémon preservando el contrato del frontend
     * (`tipo_nombre` en español además de los campos serializados).
     *
     * @return list<array<string, mixed>>
     */
    private function tiposDe(Reclutado $reclutado): array
    {
        return $reclutado->pokemon?->types
            ->map(fn (PokemonType $tipo): array => $tipo->toArray() + ['tipo_nombre' => $tipo->tipo_nombre])
            ->values()
            ->all() ?? [];
    }
}
