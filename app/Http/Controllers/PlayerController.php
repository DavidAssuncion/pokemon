<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Datagrid\DatagridService;
use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\ExploracionActiva;
use App\Models\PlayerInventory;
use App\Models\Reclutable;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use App\Support\EquiposVistaPresenter;
use App\Support\ItemCatalogo;
use App\Support\ReclutadoSerializer;
use Illuminate\View\View;
use Src\Equipos\App\ObtenerEquipos;
use Src\Exploraciones\Domain\Collections\RolesExploracionCollection;
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
        $teams = Team::with('members.reclutado.pokemon.abilities')
            ->get()
            ->sortBy('id')
            ->values();

        $teams = $teams->map(function (Team $team): array {
            $members = $team->members->sortBy('slot')->values();

            $sinergia = null;
            if ($members->count() >= 3) {
                // RFC: la sinergia del equipo se calcula con los roles
                // INDIVIDUALES de los reclutados miembro (reclutado->rol()),
                // no con team_members.behavior.
                // Equipos de 4-5: la tabla solo cubre pares/tríos → null
                // (no mostrar sinergia), aceptable.
                $roles = new RolesExploracionCollection(
                    $members->map(
                        fn (TeamMember $m): RolExploracion => $m->reclutado?->rol() ?? RolExploracion::COMBATIENTE
                    )->all()
                );
                $sinergia = SinergiaEquipo::sinergiaPara($roles);
            }

            return [
                'id' => $team->id,
                'name' => $team->name,
                'formacion' => $team->formacion ?? [],
                'posturas' => $team->posturas ?? [],
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
                'sinergia' => $sinergia?->toArray(),
                'sinergia_nombre' => $sinergia?->nombre,
            ];
        })->all();

        $reclutadosQuery = Reclutado::with([
            'pokemon.types',
            'pokemon.stats',
            'pokemon.abilities',
            'pokemon.moves',
            'teamMember',
        ])->get();

        $teamIds = $reclutadosQuery->filter(
            fn (Reclutado $r): bool => $r->teamMember !== null
        )->pluck('id')->toArray();

        $reclutados = $reclutadosQuery
            ->map(fn (Reclutado $reclutado): array => ReclutadoSerializer::serializar($reclutado, auth()->user()?->nivel()))
            ->values();

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

        return view('equipos.index', array_merge([
            'teams' => $teams,
            'reclutados' => $reclutados,
            'teamIds' => $teamIds,
            'equiposEnExploracion' => $equiposEnExploracion,
            'reclutadosEnExploracion' => $reclutadosEnExploracion,
            'inventarioCaramelos' => $this->inventarioCaramelos((int) auth()->id()),
            'tipos' => TipoEnum::options(),
        ], EquiposVistaPresenter::toView()));
    }

    /**
     * Caramelos EV del jugador para mostrar el stock de cada atributo en el
     * detalle del reclutado. Los caramelos de tipo se consultan dentro de las
     * opciones de evolución de cada Pokémon.
     *
     * @return array{
     *     ev: list<array{key: string, nombre: string, corto: string, slug: string, cantidad: int}>
     * }
     */
    private function inventarioCaramelos(int $userId): array
    {
        $items = PlayerInventory::query()
            ->where('user_id', $userId)
            ->where('item_key', 'like', 'ev:%')
            ->pluck('cantidad', 'item_key');

        $ev = collect(StatEnum::cases())
            ->map(fn (StatEnum $stat): array => [
                'key' => match ($stat) {
                    StatEnum::HP => 'hp',
                    StatEnum::ATTACK => 'atk',
                    StatEnum::DEFENSE => 'def',
                    StatEnum::SPECIAL_ATTACK => 'spAtk',
                    StatEnum::SPECIAL_DEFENSE => 'spDef',
                    StatEnum::SPEED => 'speed',
                },
                'nombre' => $stat->label(),
                'corto' => match ($stat) {
                    StatEnum::HP => 'PS',
                    StatEnum::ATTACK => 'ATK',
                    StatEnum::DEFENSE => 'DEF',
                    StatEnum::SPECIAL_ATTACK => 'A.Esp',
                    StatEnum::SPECIAL_DEFENSE => 'D.Esp',
                    StatEnum::SPEED => 'VEL',
                },
                'slug' => $stat->slug(),
                'cantidad' => (int) ($items[ItemCatalogo::keyEv($stat->value)] ?? 0),
            ])
            ->filter(fn (array $item): bool => $item['cantidad'] > 0)
            ->values()
            ->all();

        return [
            'ev' => $ev,
        ];
    }
}
