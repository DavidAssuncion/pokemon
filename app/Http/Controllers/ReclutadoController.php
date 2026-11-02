<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StatEnum;
use App\Jobs\ActualizarPokedexJob;
use App\Models\Favorito;
use App\Models\PlayerInventory;
use App\Models\PlayerMoveUnlock;
use App\Models\Pokemon;
use App\Models\Reclutado;
use App\Support\ItemCatalogo;
use App\Support\ReclutadoSerializer;
use App\Support\ReclutadoVistaPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Src\Exploraciones\App\FabricaCapacidadesStats;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Reclutamiento\App\Collections\PokemonCollection;
use Src\Reclutamiento\App\ServicioEvolucion;
use Src\Shared\Domain\ReglasEv;

class ReclutadoController extends Controller
{
    public function show(Reclutado $reclutado): View
    {
        return view('reclutado.show', ReclutadoVistaPresenter::toView(
            $reclutado,
            auth()->id() !== null ? (int) auth()->id() : null,
        ));
    }

    public function darCaramelo(Request $request, Reclutado $reclutado): JsonResponse
    {
        $data = $request->validate([
            'tipo' => 'required|string',
            'evolved_species_id' => 'sometimes|integer|nullable',
            'cantidad' => 'sometimes|integer|min:1|max:100',
        ]);

        // La exploración es individual: solo el reclutado que explora queda
        // bloqueado, no el resto de su equipo.
        if ($reclutado->estaEnExploracion()) {
            return response()->json([
                'error' => 'No se puede dar caramelos a un Pokémon en exploración',
            ], 422);
        }

        $tipo = $data['tipo'];
        $destino = $this->resolverDestino($reclutado, $data['evolved_species_id'] ?? null);

        if ($destino === null) {
            return response()->json(['error' => 'No hay evolución disponible'], 422);
        }

        $tiposRequeridos = ServicioEvolucion::tiposRequeridos($destino);

        if (! $tiposRequeridos->contiene($tipo)) {
            return response()->json(['error' => 'Ese tipo no es necesario para la evolución'], 422);
        }

        $userId = (int) auth()->id();

        $cantidadSolicitada = (int) ($data['cantidad'] ?? 1);
        $umbral = ServicioEvolucion::umbralParaNivel(ServicioEvolucion::nivelDe($reclutado));
        $faltante = max(0, $umbral - $reclutado->exp->expTipo($tipo));

        if ($faltante <= 0) {
            return response()->json([
                'error' => "Ya no necesita más caramelos de tipo {$tipo}",
            ], 422);
        }

        // Cada caramelo aporta 100 exp: no gastar más de lo que falta para el umbral.
        $cantidad = min($cantidadSolicitada, (int) ceil($faltante / 100));

        $resultado = DB::transaction(function () use ($tipo, $userId, $cantidad): ?array {
            // Lock de la fila del inventario del jugador: evita doble consumo
            // concurrente (decrement atómico tras el chequeo de stock).
            $caramelo = PlayerInventory::where('user_id', $userId)
                ->where('item_key', ItemCatalogo::keyTipo($tipo))
                ->lockForUpdate()
                ->first();

            if ($caramelo === null || $caramelo->cantidad <= 0) {
                return null;
            }

            $consumidos = min($cantidad, $caramelo->cantidad);
            $caramelo->decrement('cantidad', $consumidos);

            return [
                'caramelo' => $caramelo,
                'consumidos' => $consumidos,
            ];
        });

        if ($resultado === null) {
            return response()->json(['error' => "No hay caramelos de tipo {$tipo}"], 422);
        }

        // Exp de tipo al JSON del reclutado (cast inmutable: se reasigna).
        $reclutado->exp = $reclutado->exp->sumarExpTipo($tipo, 100 * $resultado['consumidos']);
        $reclutado->save();

        return response()->json([
            'success' => true,
            'actual' => $reclutado->exp->expTipo($tipo),
            'caramelos_disponibles' => $resultado['caramelo']->cantidad,
            'cantidad_consumida' => $resultado['consumidos'],
            'exp_ganada' => 100 * $resultado['consumidos'],
            'puede_evolucionar' => ServicioEvolucion::puedeEvolucionarPara($reclutado, $userId, $destino),
        ]);
    }

    /**
     * Da caramelos EV (+10 al stat por caramelo) a un reclutado.
     *
     * - Consume hasta `cantidad` caramelos `candy_ev/{slug}` del inventario
     *   (lockForUpdate antifraude).
     * - Cap por stat: 255 (sin cap total combinado, contrato del frontend); el
     *   chequeo es ANTES de consumir los caramelos.
     * - Rechazado con 422 si el stat ya está al máximo.
     * - Mismo patrón anti-exploración que darCaramelo.
     */
    public function darEvs(Request $request, Reclutado $reclutado): JsonResponse
    {
        $data = $request->validate([
            'stat' => 'required|string|in:hp,atk,def,spAtk,spDef,speed',
            'cantidad' => 'sometimes|integer|min:1|max:100',
        ]);

        if ($reclutado->estaEnExploracion()) {
            return response()->json([
                'error' => 'No se puede dar caramelos a un Pokémon en exploración',
            ], 422);
        }

        $stat = (string) $data['stat'];
        $statEnum = StatEnum::fromKey($stat) ?? throw new \InvalidArgumentException("Stat inválido: {$stat}");

        $userId = (int) auth()->id();

        $cantidadSolicitada = (int) ($data['cantidad'] ?? 1);

        // La fila del reclutado se bloquea antes de leer `evs`: dos peticiones
        // concurrentes deben ver el valor ya aplicado por la primera y no
        // consumir caramelos de más.
        $resultado = DB::transaction(function () use ($reclutado, $stat, $statEnum, $userId, $cantidadSolicitada): array {
            $bloqueado = Reclutado::query()
                ->lockForUpdate()
                ->findOrFail($reclutado->getKey());

            $evsActuales = $bloqueado->evs;
            $valorActual = $evsActuales->valorDe($stat);

            if ($valorActual >= ReglasEv::TOPE) {
                return ['error' => 'Ese stat ya está al máximo ('.ReglasEv::TOPE.')'];
            }

            $maxPorEv = ReglasEv::caramelosHastaTope($valorActual, $cantidadSolicitada);
            $cantidad = max(1, $maxPorEv);

            $caramelo = PlayerInventory::where('user_id', $userId)
                ->where('item_key', ItemCatalogo::keyEv($statEnum->value))
                ->lockForUpdate()
                ->first();

            if ($caramelo === null || $caramelo->cantidad <= 0) {
                return ['error' => 'No hay caramelos de EV para ese stat'];
            }

            $consumidos = min($cantidad, $caramelo->cantidad);
            $caramelo->decrement('cantidad', $consumidos);

            $nuevo = ReglasEv::aplicar($valorActual, $consumidos);
            $evsNuevos = $evsActuales->conStat($stat, $nuevo);
            $bloqueado->evs = $evsNuevos;
            $bloqueado->save();

            return [
                'reclutado' => $bloqueado,
                'caramelo' => $caramelo,
                'consumidos' => $consumidos,
                'nuevo' => $nuevo,
                'evs' => $evsNuevos,
            ];
        });

        /** @var array{error: string}|array{reclutado: Reclutado, caramelo: PlayerInventory, consumidos: int, nuevo: float, evs: StatsValue} $resultado */
        if (isset($resultado['error'])) {
            return response()->json(['error' => $resultado['error']], 422);
        }

        return response()->json([
            'success' => true,
            'actual' => (int) $resultado['nuevo'],
            'caramelos_disponibles' => $resultado['caramelo']->cantidad,
            'cantidad_consumida' => $resultado['consumidos'],
            'evs' => $resultado['evs']->toArray(),
            'stats_calculados' => ReclutadoSerializer::statsCalculados($resultado['reclutado'], auth()->user()?->nivel()),
        ]);
    }

    public function evolucionar(Request $request, Reclutado $reclutado): JsonResponse
    {
        $data = $request->validate([
            'evolved_species_id' => 'sometimes|integer|nullable',
        ]);

        $userId = auth()->id();
        $opciones = ServicioEvolucion::opcionesEvolucion($reclutado->pokemon);

        if ($opciones->isEmpty()) {
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

        $reclutado->refresh()->load('pokemon.types', 'pokemon.stats', 'pokemon.abilities', 'pokemon.moves');

        return response()->json([
            'success' => true,
            'pokemon_id' => $destino->id,
            'reclutado' => ReclutadoSerializer::serializar($reclutado, auth()->user()?->nivel()),
        ]);
    }

    /**
     * Opciones de evolución bajo demanda (usado por el modal de /equipos).
     */
    public function evoluciones(Reclutado $reclutado): JsonResponse
    {
        return response()->json([
            'opciones' => ServicioEvolucion::requisitosDeOpciones($reclutado, auth()->id())->toArray(),
        ]);
    }

    /**
     * Catálogo de movimientos bajo demanda (usado por el modal de /equipos).
     *
     * El listado de /equipos ya no envía los movimientos de cada especie para
     * mantener el payload pequeño; el modal pide sólo los del reclutado abierto.
     */
    public function movimientos(Reclutado $reclutado): JsonResponse
    {
        $reclutado->loadMissing('pokemon.moves');

        return response()->json([
            'movimientos_disponibles' => ReclutadoSerializer::movimientosDisponibles(
                $reclutado,
                auth()->user()?->nivel(),
            ),
        ]);
    }

    /**
     * Resuelve el destino de evolución devolviendo null si no se puede
     * determinar (para que el caller distinga entre "sin selección" e
     * "selección inválida").
     *
     * - Una sola opción → siempre esa (se acepta con o sin evolved_species_id).
     * - Varias opciones → exige evolved_species_id ∈ opciones.
     */
    private function resolverDestinoSeleccion(PokemonCollection $opciones, ?int $evolvedSpeciesId): ?Pokemon
    {
        $primera = $opciones->primero();

        if ($opciones->count() === 1) {
            return $evolvedSpeciesId === null || ($primera !== null && $evolvedSpeciesId === $primera->id)
                ? $primera
                : null;
        }
        if ($evolvedSpeciesId === null) {
            return null;
        }

        return $opciones->porId($evolvedSpeciesId);
    }

    /**
     * Resuelve el destino para dar caramelos. Si viene `evolved_species_id`,
     * debe pertenecer a las opciones; si no, se usa la primera.
     */
    private function resolverDestino(Reclutado $reclutado, ?int $evolvedSpeciesId): ?Pokemon
    {
        $opciones = ServicioEvolucion::opcionesEvolucion($reclutado->pokemon);
        if ($opciones->isEmpty()) {
            return null;
        }
        if ($evolvedSpeciesId === null) {
            return $opciones->primero();
        }

        return $opciones->porId($evolvedSpeciesId);
    }

    /**
     * Actualiza el loadout del reclutado: hasta 6 movimientos del catálogo de
     * su especie y una habilidad activa.
     */
    public function actualizarLoadout(Request $request, Reclutado $reclutado): JsonResponse
    {
        if ($reclutado->estaEnExploracion()) {
            return response()->json([
                'error' => 'No se puede modificar el loadout de un Pokémon en exploración',
            ], 422);
        }

        $data = $request->validate([
            'habilidad_id' => 'nullable|integer',
            'movimientos' => 'sometimes|array|max:6',
            'movimientos.*' => 'string',
        ]);

        $reclutado->loadMissing('pokemon.abilities', 'pokemon.moves');
        $pokemon = $reclutado->pokemon;

        if ($pokemon === null) {
            return response()->json(['error' => 'El reclutado no tiene Pokémon asociado'], 422);
        }

        // Distinguir "campo omitido" (conservar) de "null explícito" (volver a
        // la habilidad automática de la especie).
        $habilidadEnviada = array_key_exists('habilidad_id', $data);
        $habilidadId = $habilidadEnviada
            ? $data['habilidad_id']
            : $reclutado->habilidad_activa_id;
        if ($habilidadEnviada
            && $habilidadId !== null
            && ! $pokemon->abilities->contains('id', $habilidadId)) {
            return response()->json(['error' => 'La habilidad no pertenece a este Pokémon'], 422);
        }

        $slugs = array_values(array_unique(
            array_key_exists('movimientos', $data)
                ? $data['movimientos']
                : (is_array($reclutado->movimientos) ? $reclutado->movimientos : []),
        ));
        $disponibles = $pokemon->moves->pluck('slug')->all();
        $invalidos = array_values(array_diff($slugs, $disponibles));
        if ($invalidos !== []) {
            return response()->json([
                'error' => 'Movimientos no disponibles para este Pokémon: '.implode(', ', $invalidos),
            ], 422);
        }

        $nivelUsuario = $request->user()?->nivel() ?? 1;
        $seleccionados = $pokemon->moves->whereIn('slug', $slugs);
        $userId = (int) auth()->id();

        $desbloqueadas = array_fill_keys(
            PlayerMoveUnlock::query()
                ->withoutGlobalScope(PlayerMoveUnlock::USER_SCOPE_NAME)
                ->where('user_id', $userId)
                ->whereIn('move_id', $seleccionados->pluck('id'))
                ->pluck('move_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all(),
            true,
        );

        $bloqueadosPorNivel = [];
        $sendasBloqueadas = [];

        foreach ($seleccionados as $move) {
            $esNivel = ($move->pivot->source ?? null) === 'level-up';
            $desbloqueado = isset($desbloqueadas[(int) $move->id]);

            if ($esNivel) {
                if (! $desbloqueado && $move->nivelRequeridoEfectivo() > $nivelUsuario) {
                    $bloqueadosPorNivel[] = "{$move->name} (nivel {$move->nivelRequeridoEfectivo()})";
                }

                continue;
            }

            if (! $desbloqueado) {
                $sendasBloqueadas[] = $move->name;
            }
        }

        if ($bloqueadosPorNivel !== []) {
            return response()->json([
                'error' => 'Movimientos bloqueados por nivel: '.implode(', ', $bloqueadosPorNivel),
            ], 422);
        }

        if ($sendasBloqueadas !== []) {
            return response()->json([
                'error' => 'Movimientos de Senda sin desbloquear en la cuenta: '.implode(', ', $sendasBloqueadas),
            ], 422);
        }

        $reclutado->update([
            'habilidad_activa_id' => $habilidadId,
            'movimientos' => $slugs,
        ]);

        $reclutado->load('pokemon.abilities', 'pokemon.moves', 'pokemon.types', 'pokemon.stats');

        return response()->json([
            'success' => true,
            'reclutado' => ReclutadoSerializer::serializar($reclutado, auth()->user()?->nivel()),
        ]);
    }

    /**
     * Libera (elimina) un reclutado del usuario.
     *
     * Anti-IDOR: el route-model binding + global scope BelongsToUser de Reclutado
     * devuelven 404 para reclutados ajenos (mismo patrón que show).
     * Bloqueado con 422 si ese reclutado está en exploración (la exploración
     * es individual: un compañero de equipo puede liberarse con normalidad).
     */
    public function destroy(Reclutado $reclutado): JsonResponse
    {
        if ($reclutado->estaEnExploracion()) {
            return response()->json([
                'error' => 'No se puede liberar un Pokémon en exploración',
            ], 422);
        }

        DB::transaction(function () use ($reclutado): void {
            $reclutado->teamMember?->delete();
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
            $max = 6;
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

        $query = Favorito::with([
            'reclutado.pokemon.types',
            'reclutado.pokemon.stats',
            'reclutado.pokemon.abilities',
            'reclutado.pokemon.moves',
        ]);

        if ($habitatId > 0) {
            $query->paraHabitat($habitatId);
        } else {
            $query->globales();
        }

        $reclutados = ReclutadoSerializer::serializarColeccion(
            $query
                ->withoutGlobalScope('belongsToUser')
                ->where('user_id', $userId)
                ->get()
                ->map(fn (Favorito $favorito): Reclutado => $favorito->reclutado),
            auth()->user()?->nivel(),
            incluirMovimientosDisponibles: false,
        );

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
        $reclutados = ReclutadoSerializer::serializarColeccion(
            Reclutado::with([
                'pokemon.types',
                'pokemon.stats',
                'pokemon.abilities',
                'pokemon.moves',
            ])->get(),
            auth()->user()?->nivel(),
            incluirMovimientosDisponibles: false,
        );

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
            FabricaCapacidadesStats::desdeReclutado($reclutado, auth()->user())->todas()->toArray()
        );
    }

    /**
     * Actualiza el rol de exploración individual del reclutado (RFC) y
     * sincroniza team_members.behavior (mismo pokemon_id) para no romper los
     * contratos legacy que aún leen el rol del miembro de equipo.
     *
     * Anti-IDOR: route-model binding + global scope BelongsToUser → 404 si ajeno.
     */
    public function actualizarRol(Request $request, Reclutado $reclutado): JsonResponse
    {
        $data = $request->validate([
            'behavior' => ['required', 'string', 'in:VANGUARDIA,COMBATIENTE,RECOLECTOR,RASTREADOR'],
        ]);

        DB::transaction(function () use ($reclutado, $data): void {
            $reclutado->update(['behavior' => $data['behavior']]);

            \App\Models\TeamMember::where('pokemon_id', $reclutado->id)
                ->update(['behavior' => $data['behavior']]);
        });

        return response()->json([
            'behavior' => $reclutado->fresh()->behavior,
            'rol' => $reclutado->rol()->value,
        ]);
    }
}
