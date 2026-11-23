<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\Pokedex;
use App\Support\DesbloqueoPokedex;
use App\Support\UI\SpritesEntrenador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Src\Equipos\App\ObtenerEquipos;
use Src\Habitats\App\ActualizarNivelMinimoSpawn;
use Src\Habitats\App\AsignarFamiliaAHabitat;
use Src\Habitats\App\EliminarFamiliaDeHabitat;
use Src\Habitats\App\MoverPokemonDeNivel;
use Src\Habitats\App\ObtenerFamiliasDisponibles;
use Src\Habitats\App\ObtenerFamiliasSinHabitat;
use Src\Habitats\App\ObtenerHabitatDetalle;
use Src\Habitats\App\ObtenerHabitatsPorProvincia;
use Src\Habitats\App\ObtenerPokemonsPorHabitat;
use Src\HabitatsTemporales\App\ObtenerProvinciaTemporal;

class HabitatsController extends Controller
{
    public function __construct(
        public readonly ObtenerEquipos $obtenerEquipos,
        public readonly ObtenerHabitatsPorProvincia $obtenerHabitatsPorProvincia,
        public readonly ObtenerProvinciaTemporal $obtenerProvinciaTemporal,
        public readonly ObtenerHabitatDetalle $obtenerHabitatDetalle,
        public readonly ObtenerPokemonsPorHabitat $obtenerPokemonsPorHabitat,
        public readonly ObtenerFamiliasDisponibles $obtenerFamiliasDisponibles,
        public readonly ObtenerFamiliasSinHabitat $obtenerFamiliasSinHabitat,
        public readonly AsignarFamiliaAHabitat $asignarFamiliaAHabitat,
        public readonly EliminarFamiliaDeHabitat $eliminarFamiliaDeHabitat,
        public readonly MoverPokemonDeNivel $moverPokemonDeNivel,
        public readonly ActualizarNivelMinimoSpawn $actualizarNivelMinimoSpawn,
    ) {
    }

    public function index(): View
    {
        $provincias = $this->obtenerHabitatsPorProvincia->handle();
        //Quitar Vacio de las provincias, ya que no tiene hábitats asignados y no se quiere mostrar en la vista.
        $provincias = $provincias->filter(fn ($provincia) => $provincia->name !== 'Vacio');

        $provinciaTemporal = $this->obtenerProvinciaTemporal->obtener((int) Auth::id());

        return view('habitats.index', [
            'provincias' => array_values($provincias->toArray()),
            'provinciaTemporal' => $provinciaTemporal?->toArray(),
        ]);
    }

    public function show(Request $request, int $id): View
    {
        $unlockAll = DesbloqueoPokedex::activo($request);

        $exploracionesActivas = ExploracionActiva::where('habitat_id', $id)
            ->whereNull('regreso')
            ->with('reclutado.pokemon')
            ->get();

        // La exploración es individual: se exponen los IDs de los reclutados
        // que están explorando para que la vista bloquee solo a esos.
        $reclutadosEnExploracion = ExploracionActiva::whereNull('regreso')
            ->pluck('reclutado_id')
            ->all();

        // Get sighted Pokemon IDs from Pokedex for this habitat
        $habitatData = $this->obtenerHabitatDetalle->handle($id)->toArray();
        $pokemonIds = [];
        if (! empty($habitatData['levels'])) {
            foreach ($habitatData['levels'] as $levelPokemon) {
                foreach ($levelPokemon as $pokemon) {
                    $pokemonIds[] = $pokemon['id'];
                }
            }
        }
        $sightedPokemonIds = $unlockAll
            ? array_values(array_unique($pokemonIds))
            : Pokedex::whereIn('pokemon_id', $pokemonIds)
                ->where('visto', true)
                ->pluck('pokemon_id')
                ->toArray();

        return view('habitats.show', [
            'habitat' => $habitatData,
            'teams' => $this->obtenerEquipos->run()->all(),
            'exploracionesActivas' => $exploracionesActivas,
            // @deprecated Compatibilidad temporal con la vista actual; se
            // sustituirá por `reclutadosEnExploracion` en el bloque de frontend.
            'equiposEnExploracion' => collect(),
            'reclutadosEnExploracion' => $reclutadosEnExploracion,
            'sightedPokemonIds' => $sightedPokemonIds,
            'unlockAll' => $unlockAll,
            'sprites' => SpritesEntrenador::manifiesto(),
        ]);
    }

    public function pokemon(int $habitat): JsonResponse
    {
        $pokemon = $this->obtenerPokemonsPorHabitat->handle($habitat);

        return response()->json($pokemon->toArray());
    }

    public function families(int $id): JsonResponse
    {
        $families = $this->obtenerFamiliasDisponibles->handle($id);

        return response()->json($families->toArray());
    }

    public function assignFamily(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'evolution_chain_id' => ['required', 'integer', 'exists:pokemon,evolution_chain_id'],
        ]);

        try {
            $result = $this->asignarFamiliaAHabitat->handle($id, (int) $validated['evolution_chain_id']);

            return response()->json($result->toArray(), 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function removeFamily(int $id, int $chainId): JsonResponse
    {
        $validated = Validator::make(
            ['evolution_chain_id' => $chainId],
            ['evolution_chain_id' => ['required', 'integer', 'exists:pokemon,evolution_chain_id']]
        );

        if ($validated->fails()) {
            return response()->json(['errors' => $validated->errors()], 422);
        }

        try {
            $result = $this->eliminarFamiliaDeHabitat->handle($id, $chainId);

            return response()->json($result->toArray());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function unassignedFamilies(): JsonResponse
    {
        $families = $this->obtenerFamiliasSinHabitat->handle();

        return response()->json($families->toArray());
    }

    public function movePokemonLevel(int $habitat, int $pokemon, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'level' => ['required', 'integer', 'between:1,3'],
        ]);

        try {
            $result = $this->moverPokemonDeNivel->handle($habitat, $pokemon, (int) $validated['level']);

            return response()->json($result->toArray());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function updateMinLevel(int $habitat, int $pokemon, Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'min_level' => ['required_if:manual,true,1', 'integer', 'between:1,100'],
            'manual' => ['sometimes', 'boolean'],
        ])->validate();

        try {
            $manual = (bool) ($validated['manual'] ?? true);

            $result = $manual
                ? $this->actualizarNivelMinimoSpawn->handle(
                    habitatId: $habitat,
                    pokemonId: $pokemon,
                    minLevel: (int) ($validated['min_level'] ?? 1),
                    manual: true,
                )
                : $this->actualizarNivelMinimoSpawn->automatico($habitat, $pokemon);

            $detalle = $this->obtenerHabitatDetalle->handle($habitat)->toArray();

            return response()->json(array_merge($result->toArray(), [
                'min_lvls' => [
                    1 => $detalle['min_lvl_1'],
                    2 => $detalle['min_lvl_2'],
                    3 => $detalle['min_lvl_3'],
                ],
            ]));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Legacy no-op conservado por compatibilidad de API.
     *
     * @deprecated Desde TASK-016. No tiene efecto: responde siempre
     *             `{"favorito": false, "count": 0}`. Los favoritos ahora son
     *             por reclutado dentro de un hábitat, no de hábitat en solitario.
     *             Alternativa: `POST /api/reclutados/{reclutado}/toggle-favorito`
     *             con body `{"habitat_id": <id>}`.
     *
     * @see \App\Http\Controllers\ReclutadoController::toggleFavorito()
     */
    public function toggleFavorito(Habitat $habitat): JsonResponse
    {
        return response()->json(['favorito' => false, 'count' => 0]);
    }
}
