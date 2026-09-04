<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ActualizarPokedexJob;
use App\Models\Favorito;
use App\Models\PlayerInventory;
use App\Models\Pokemon;
use App\Models\Reclutado;
use App\Support\ItemCatalogo;
use App\Support\ReclutadoSerializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Src\Exploraciones\Domain\CapacidadesStats;
use Src\Reclutamiento\App\ServicioEvolucion;
use Src\Shared\Domain\NivelHelper;

class ReclutadoController extends Controller
{
    public function show(Reclutado $reclutado): View
    {
        $reclutado->load('pokemon.types');

        $siguiente = ServicioEvolucion::siguienteEvolucion($reclutado->pokemon);
        $expTotal = $reclutado->exp->total();

        return view('reclutado.show', [
            'reclutado' => [
                'id' => $reclutado->id,
                'nombre' => $reclutado->nombre,
                'pokemon_id' => $reclutado->pokemon_id,
                'pokemon_nombre' => $reclutado->pokemon?->name,
                'nivel' => NivelHelper::nivelDesdeExperiencia($expTotal),
                'exp_total' => $expTotal,
                'imagen' => "/images/iconos/{$reclutado->pokemon_id}.png",
            ],
            'siguiente' => $siguiente !== null
                ? [
                    'pokemon_id' => $siguiente->id,
                    'nombre' => $siguiente->name,
                    'imagen' => "/images/iconos/{$siguiente->id}.png",
                ]
                : null,
            'requisitos' => ServicioEvolucion::requisitos($reclutado, auth()->id()),
            'puedeEvolucionar' => ServicioEvolucion::puedeEvolucionar($reclutado, auth()->id()),
        ]);
    }

    public function darCaramelo(Request $request, Reclutado $reclutado): JsonResponse
    {
        $data = $request->validate([
            'tipo' => 'required|string',
            'evolved_species_id' => 'sometimes|integer|nullable',
        ]);

        // Coherencia con liberar/evolucionar: un pokémon en equipo en
        // exploración no puede recibir caramelos.
        if ($reclutado->teamMember?->team?->isExploring()) {
            return response()->json([
                'error' => 'No se puede dar caramelos a un pokémon de un equipo en exploración',
            ], 422);
        }

        $tipo = $data['tipo'];
        $destino = $this->resolverDestino($reclutado, $data['evolved_species_id'] ?? null);

        if ($destino === null) {
            return response()->json(['error' => 'No hay evolución disponible'], 422);
        }

        $tiposRequeridos = ServicioEvolucion::tiposRequeridos($destino);

        if (! in_array($tipo, $tiposRequeridos, true)) {
            return response()->json(['error' => 'Ese tipo no es necesario para la evolución'], 422);
        }

        $userId = auth()->id();

        $caramelo = DB::transaction(function () use ($tipo, $userId): ?PlayerInventory {
            // Lock de la fila del inventario del jugador: evita doble consumo
            // concurrente (decrement atómico tras el chequeo de stock).
            $caramelo = PlayerInventory::where('user_id', $userId)
                ->where('item_key', ItemCatalogo::keyTipo($tipo))
                ->lockForUpdate()
                ->first();

            if ($caramelo === null || $caramelo->cantidad <= 0) {
                return null;
            }

            $caramelo->decrement('cantidad');

            return $caramelo;
        });

        if ($caramelo === null) {
            return response()->json(['error' => "No hay caramelos de tipo {$tipo}"], 422);
        }

        // Exp de tipo al JSON del reclutado (cast inmutable: se reasigna).
        $reclutado->exp = $reclutado->exp->sumarExpTipo($tipo, 100);
        $reclutado->save();

        return response()->json([
            'success' => true,
            'actual' => $reclutado->exp->expTipo($tipo),
            'caramelos_disponibles' => $caramelo->cantidad,
            'puede_evolucionar' => ServicioEvolucion::puedeEvolucionarPara($reclutado, $userId, $destino),
        ]);
    }

    public function evolucionar(Request $request, Reclutado $reclutado): JsonResponse
    {
        $data = $request->validate([
            'evolved_species_id' => 'sometimes|integer|nullable',
        ]);

        $userId = auth()->id();
        $opciones = ServicioEvolucion::opcionesEvolucion($reclutado->pokemon);

        if ($opciones === []) {
            return response()->json(['error' => 'No hay evolución disponible'], 422);
        }

        $destino = $this->resolverDestinoSeleccion($opciones, $data['evolved_species_id'] ?? null);

        if ($destino === null) {
            // Hay selección explícita (inválida) → destino inválido; si no, exige elegir.
            $haySeleccion = array_key_exists('evolved_species_id', $data) && $data['evolved_species_id'] !== null;
            $error = $haySeleccion ? 'Evolución no válida' : 'Selecciona a qué pokémon evolucionar';

            return response()->json(['error' => $error], 422);
        }

        if (! ServicioEvolucion::puedeEvolucionarPara($reclutado, $userId, $destino)) {
            return response()->json(['error' => 'No cumple los requisitos'], 422);
        }

        $umbral = ServicioEvolucion::umbralParaNivel(ServicioEvolucion::nivelDe($reclutado));
        $tipos = ServicioEvolucion::tiposRequeridos($destino);

        DB::transaction(function () use ($reclutado, $destino, $tipos, $umbral): void {
            ServicioEvolucion::consumirExpTipo($reclutado, $tipos, $umbral);
            $reclutado->update(['pokemon_id' => $destino->id]);
        });

        ActualizarPokedexJob::dispatch($userId, $destino->id, 'RECLUTADO');

        return response()->json(['success' => true, 'pokemon_id' => $destino->id]);
    }

    /**
     * Opciones de evolución bajo demanda (usado por el modal de /equipos).
     */
    public function evoluciones(Reclutado $reclutado): JsonResponse
    {
        return response()->json([
            'opciones' => ServicioEvolucion::requisitosDeOpciones($reclutado, auth()->id()),
        ]);
    }

    /**
     * Resuelve el destino de evolución devolviendo null si no se puede
     * determinar (para que el caller distinga entre "sin selección" e
     * "selección inválida").
     *
     * - Una sola opción → siempre esa (se acepta con o sin evolved_species_id).
     * - Varias opciones → exige evolved_species_id ∈ opciones.
     *
     * @param  array<int, Pokemon>  $opciones
     */
    private function resolverDestinoSeleccion(array $opciones, ?int $evolvedSpeciesId): ?Pokemon
    {
        if (count($opciones) === 1) {
            return $evolvedSpeciesId === null || $evolvedSpeciesId === $opciones[0]->id ? $opciones[0] : null;
        }
        if ($evolvedSpeciesId === null) {
            return null;
        }
        foreach ($opciones as $opcion) {
            if ($opcion->id === $evolvedSpeciesId) {
                return $opcion;
            }
        }

        return null;
    }

    /**
     * Resuelve el destino para dar caramelos. Si viene `evolved_species_id`,
     * debe pertenecer a las opciones; si no, se usa la primera.
     */
    private function resolverDestino(Reclutado $reclutado, ?int $evolvedSpeciesId): ?Pokemon
    {
        $opciones = ServicioEvolucion::opcionesEvolucion($reclutado->pokemon);
        if ($opciones === []) {
            return null;
        }
        if ($evolvedSpeciesId === null) {
            return $opciones[0];
        }
        foreach ($opciones as $opcion) {
            if ($opcion->id === $evolvedSpeciesId) {
                return $opcion;
            }
        }

        return null;
    }

    /**
     * Libera (elimina) un reclutado del usuario.
     *
     * Anti-IDOR: el route-model binding + global scope BelongsToUser de Reclutado
     * devuelven 404 para reclutados ajenos (mismo patrón que show).
     * Bloqueado con 422 si el reclutado pertenece a un equipo en exploración.
     */
    public function destroy(Reclutado $reclutado): JsonResponse
    {
        $teamMember = $reclutado->teamMember;

        if ($teamMember?->team?->isExploring()) {
            return response()->json([
                'error' => 'No se puede liberar un pokémon de un equipo en exploración',
            ], 422);
        }

        DB::transaction(function () use ($reclutado, $teamMember): void {
            $teamMember?->delete();
            $reclutado->delete();
        });

        return response()->json(['success' => true]);
    }

    /**
     * Alterna el marcador de favorito del reclutado del usuario autenticado.
     *
     * - `habitat_id = null` → favorito global (límite 6 por usuario).
     * - `habitat_id = N` → favorito dentro de ese hábitat (límite 6 por hábitat).
     *
     * Anti-IDOR: route-model binding + global scope BelongsToUser → 404 si ajeno.
     */
    public function toggleFavorito(Request $request, Reclutado $reclutado): JsonResponse
    {
        $data = $request->validate([
            'habitat_id' => 'nullable|integer|exists:habitats,id',
        ]);

        $habitatId = $data['habitat_id'] ?? null;
        $userId = (int) auth()->id();

        // Antes de añadir, validar el límite del alcance (7º → 422).
        if (! Favorito::esFavorito($userId, $reclutado->id, $habitatId)) {
            $max = $habitatId === null ? 6 : 6;
            $actual = $habitatId === null
                ? Favorito::countGlobales($userId)
                : Favorito::countParaHabitat($userId, $habitatId);

            if ($actual >= $max) {
                $mensaje = $habitatId === null
                    ? 'Máximo 6 favoritos globales.'
                    : 'Máximo 6 favoritos para este hábitat.';

                return response()->json(['message' => $mensaje], 422);
            }
        }

        $favorito = Favorito::toggle($userId, $reclutado->id, $habitatId);
        $count = $habitatId === null
            ? Favorito::countGlobales($userId)
            : Favorito::countParaHabitat($userId, $habitatId);

        return response()->json(['favorito' => $favorito, 'count' => $count]);
    }

    /**
     * Lista los reclutados favoritos del usuario autenticado, serializados con
     * el mismo formato que /equipos (ReclutadoSerializer compartido).
     *
     * - Sin query param → favoritos globales (habitat_id null).
     * - Con `?habitat_id=N` → favoritos de ese hábitat.
     */
    public function listarFavoritos(Request $request): JsonResponse
    {
        $habitatId = $request->integer('habitat_id');
        $userId = (int) auth()->id();

        $query = Favorito::with(['reclutado.pokemon.types', 'reclutado.pokemon.stats']);

        if ($habitatId > 0) {
            $query->paraHabitat($habitatId);
        } else {
            $query->globales();
        }

        $reclutados = $query
            ->withoutGlobalScope('belongsToUser')
            ->where('user_id', $userId)
            ->get()
            ->map(fn (Favorito $favorito): array => ReclutadoSerializer::serializar($favorito->reclutado))
            ->values();

        return response()->json($reclutados);
    }

    /**
     * Lista todos los reclutados del usuario autenticado, serializados con el
     * mismo formato que /equipos (ReclutadoSerializer compartido). El modal de
     * gestión de favoritos del hábitat lo usa para mostrar "todos" los reclutados.
     *
     * Anti-IDOR: el global scope BelongsToUser filtra por el usuario autenticado.
     */
    public function listarTodos(): JsonResponse
    {
        $reclutados = Reclutado::with(['pokemon.types', 'pokemon.stats'])
            ->get()
            ->map(fn (Reclutado $reclutado): array => ReclutadoSerializer::serializar($reclutado))
            ->values();

        return response()->json($reclutados);
    }

    /**
     * Capacidades del reclutado (stats + niveles) para el detalle de
     * exploración individual. Anti-IDOR: route-model binding + scope.
     */
    public function capacidades(Reclutado $reclutado): JsonResponse
    {
        $reclutado->loadMissing('pokemon.stats', 'pokemon.types');

        return response()->json(
            CapacidadesStats::desdeReclutado($reclutado, auth()->user())->todas()
        );
    }
}
