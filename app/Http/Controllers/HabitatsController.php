<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\Pokedex;
use App\Support\DesbloqueoPokedex;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Src\Equipos\App\ObtenerEquipos;
use Src\Habitats\App\AsignarFamiliaAHabitat;
use Src\Habitats\App\EliminarFamiliaDeHabitat;
use Src\Habitats\App\MoverPokemonDeNivel;
use Src\Habitats\App\ObtenerFamiliasDisponibles;
use Src\Habitats\App\ObtenerFamiliasSinHabitat;
use Src\Habitats\App\ObtenerHabitatDetalle;
use Src\Habitats\App\ObtenerHabitatsPorProvincia;
use Src\Habitats\App\ObtenerPokemonsPorHabitat;

class HabitatsController extends Controller
{
    public function __construct(
        public readonly ObtenerEquipos $obtenerEquipos,
        public readonly ObtenerHabitatsPorProvincia $obtenerHabitatsPorProvincia,
        public readonly ObtenerHabitatDetalle $obtenerHabitatDetalle,
        public readonly ObtenerPokemonsPorHabitat $obtenerPokemonsPorHabitat,
        public readonly ObtenerFamiliasDisponibles $obtenerFamiliasDisponibles,
        public readonly ObtenerFamiliasSinHabitat $obtenerFamiliasSinHabitat,
        public readonly AsignarFamiliaAHabitat $asignarFamiliaAHabitat,
        public readonly EliminarFamiliaDeHabitat $eliminarFamiliaDeHabitat,
        public readonly MoverPokemonDeNivel $moverPokemonDeNivel,
    ) {
    }

    public function index(): View
    {
        $provincias = $this->obtenerHabitatsPorProvincia->handle();
        //Quitar Vacio de las provincias, ya que no tiene hábitats asignados y no se quiere mostrar en la vista.
        $provincias = $provincias->filter(fn ($provincia) => $provincia->name !== 'Vacio');

        return view('habitats.index', ['provincias' => $provincias->toArray()]);
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
                    $pokemonIds[] = $pokemon['id'] ?? $pokemon['species_id'] ?? 0;
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

    /**
     * Legacy no-op: el frontend actual marca hábitats como favoritos a través de
     * `POST /api/reclutados/{reclutado}/toggle-favorito` con body `habitat_id`.
     * Este endpoint queda como compatibilidad sin efecto (los favoritos ahora son
     * por reclutado dentro de un hábitat, no de hábitat en solitario).
     */
    public function toggleFavorito(Habitat $habitat): JsonResponse
    {
        return response()->json(['favorito' => false, 'count' => 0]);
    }
}
