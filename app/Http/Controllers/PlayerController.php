<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Datagrid\DatagridService;
use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\ExploracionActiva;
use App\Models\PlayerInventory;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use App\Support\DesbloqueoPokedex;
use App\Support\EquiposVistaPresenter;
use App\Support\ItemCatalogo;
use App\Support\Piezas\FabricaPiezasReclutado;
use App\Support\ReclutadoSerializer;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Src\Equipos\App\ObtenerEquipos;
use Src\Exploraciones\Domain\Collections\RolesExploracionCollection;
use Src\Exploraciones\Domain\RolExploracion;
use Src\Exploraciones\Domain\SinergiaEquipo;
use Src\Reclutamiento\App\ConstructorReclutablesResumen;

class PlayerController extends Controller
{
    public function __construct(
        public readonly ObtenerEquipos $obtenerEquipos,
        private readonly DatagridService $datagrid,
        private readonly ConstructorReclutablesResumen $constructorReclutables,
        private readonly FabricaPiezasReclutado $fabricaPiezas,
    ) {
    }

    public function pokedex(Request $request): View
    {
        // TEMPORAL: flag de QA para ver todos los Pokémon y sus hábitats sin
        // haberlos avistado. Se puede activar por entorno o con ?unlock=1 en
        // local/debug, sin tocar los datos de la pokédex del usuario.
        $unlockAll = DesbloqueoPokedex::activo($request);

        $params = [
            'per_page' => 120,
            'sort' => 'id',
            'order' => 'asc',
        ];

        if (! $unlockAll) {
            $params['filter'] = ['visto' => '1'];
        }

        $page = $this->datagrid->list('pokemon', $params);

        return view('pokedex.index', [
            'pokemons' => $page,
            'counts' => $page['meta']['counts'] ?? ['total' => 0, 'vistos' => 0, 'atrapados' => 0, 'no_vistos' => 0],
            'tipos' => TipoEnum::options(),
            'stats' => StatEnum::options(),
            'unlockAll' => $unlockAll,
        ]);
    }

    public function reclutamiento(Request $request): View
    {
        return view('reclutamiento.index', [
            'reclutables' => $this->constructorReclutables->todos((int) $request->user()?->getAuthIdentifier())->toArray(),
        ]);
    }

    public function equipos(): View
    {
        $teams = Team::with('members.reclutado.pokemon.abilities')
            ->orderBy('id')
            ->get();

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

        $reclutados = ReclutadoSerializer::serializarColeccion(
            $reclutadosQuery,
            auth()->user()?->nivel(),
            incluirMovimientosDisponibles: false,
            piezas: $this->fabricaPiezas->paraListado((int) auth()->id(), $reclutadosQuery),
        );

        $reclutadosEnExploracion = ExploracionActiva::whereNull('regreso')
            ->pluck('reclutado_id')
            ->toArray();

        return view('equipos.index', array_merge([
            'teams' => $teams,
            'reclutados' => $reclutados,
            'teamIds' => $teamIds,
            // @deprecated Compatibilidad temporal con la vista: la exploración
            // ya es individual y se expone en `reclutadosEnExploracion`.
            'equiposEnExploracion' => [],
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
