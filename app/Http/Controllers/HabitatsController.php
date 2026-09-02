<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\HabitatFavorito;
use App\Models\Pokedex;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $provincias = $this->obtenerHabitatsPorProvincia->handle()->toArray();

        return view('habitats.index', ['provincias' => $provincias]);
    }

    public function show(int $id): View
    {
        $exploracionesActivas = ExploracionActiva::where('habitat_id', $id)
            ->whereNull('regreso')
            ->with('reclutado')
            ->get();

        // Equipos cuyo miembro está en exploración activa (bloqueo de envío).
        $expMap = ExploracionActiva::whereNull('regreso')
            ->get()
            ->keyBy('reclutado_id');
        $reclutadoIds = $expMap->keys()->toArray();
        $equiposEnExploracion = $reclutadoIds !== []
            ? \App\Models\TeamMember::whereIn('pokemon_id', $reclutadoIds)
                ->get()
                ->map(fn (\App\Models\TeamMember $miembro) => [
                    'equipo_id' => $miembro->team_id,
                    'habitat_id' => $expMap->get($miembro->pokemon_id)?->habitat_id,
                    'habitat_name' => $expMap->get($miembro->pokemon_id)?->habitat?->name ?? 'Desconocido',
                ])
            : collect();

        // Get sighted Pokemon IDs from Pokedex for this habitat
        $habitatData = $this->obtenerHabitatDetalle->handle($id);
        $pokemonIds = [];
        if (! empty($habitatData->toArray()['levels'])) {
            foreach ($habitatData->toArray()['levels'] as $levelPokemon) {
                foreach ($levelPokemon as $pokemon) {
                    $pokemonIds[] = $pokemon['id'] ?? $pokemon['species_id'] ?? 0;
                }
            }
        }
        $sightedPokemonIds = Pokedex::whereIn('pokemon_id', $pokemonIds)
            ->where('visto', true)
            ->pluck('pokemon_id')
            ->toArray();

        return view('habitats.show', [
            'habitat' => $habitatData->toArray(),
            'teams' => $this->obtenerEquipos->run(),
            'exploracionesActivas' => $exploracionesActivas,
            'equiposEnExploracion' => $equiposEnExploracion,
            'sightedPokemonIds' => $sightedPokemonIds,
        ]);
    }

    public function pokemon(int $habitat): JsonResponse
    {
        $pokemon = $this->obtenerPokemonsPorHabitat->handle($habitat);

        return response()->json($pokemon);
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
     * Añade o elimina el hábitat de los favoritos del usuario autenticado.
     * Límite: 6 hábitats favoritos por usuario (7º → 422).
     */
    public function toggleFavorito(Habitat $habitat): JsonResponse
    {
        $userId = (int) Auth::id();
        $favorito = HabitatFavorito::where('user_id', $userId)
            ->where('habitat_id', $habitat->id)
            ->first();

        if ($favorito !== null) {
            $favorito->delete();

            return response()->json([
                'favorito' => false,
                'count' => $this->countFavoritos($userId),
            ]);
        }

        $count = $this->countFavoritos($userId);
        if ($count >= HabitatFavorito::MAX_FAVORITOS) {
            return response()->json(['message' => 'Máximo 6 hábitats favoritos'], 422);
        }

        HabitatFavorito::create([
            'user_id' => $userId,
            'habitat_id' => $habitat->id,
        ]);

        return response()->json([
            'favorito' => true,
            'count' => $count + 1,
        ], 201);
    }

    private function countFavoritos(int $userId): int
    {
        return HabitatFavorito::where('user_id', $userId)->count();
    }
}
