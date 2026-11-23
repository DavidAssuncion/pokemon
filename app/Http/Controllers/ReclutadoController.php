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
use App\Models\User;
use App\Support\ItemCatalogo;
use App\Support\Piezas\FabricaPiezasReclutado;
use App\Support\ReclutadoSerializer;
use App\Support\ReclutadoVistaPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Src\Exploraciones\App\FabricaCapacidadesStats;
use Src\Exploraciones\Domain\RolExploracion;
use Src\Pokemon\App\ServicioVersiones;
use Src\Reclutamiento\App\Collections\PokemonCollection;
use Src\Reclutamiento\App\ServicioEvolucion;
use Src\Reclutamiento\Domain\Collections\RequisitoEvolucionCollection;
use Src\Reclutamiento\Domain\DataTransferObjects\RequisitoEvolucion;
use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;
use Src\Shared\Domain\ReglasCaramelo;
use Src\Shared\Domain\ReglasEv;

class ReclutadoController extends Controller
{
    public function __construct(
        private readonly FabricaPiezasReclutado $fabricaPiezas,
    ) {
    }

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
            'version_pokemon_id' => 'sometimes|integer|nullable',
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
        $versionPokemonId = isset($data['version_pokemon_id']) ? (int) $data['version_pokemon_id'] : 0;
        $esVersion = $versionPokemonId > 0;

        if ($esVersion) {
            $destino = Pokemon::find($versionPokemonId);

            if ($destino === null || ! ServicioVersiones::sonDelMismoGrupo($reclutado->pokemon_id, $destino->id)) {
                return response()->json(['error' => 'Forma no válida'], 422);
            }
        } else {
            $destino = $this->resolverDestino($reclutado, $data['evolved_species_id'] ?? null);
        }

        if ($destino === null) {
            return response()->json(['error' => 'No hay evolución disponible'], 422);
        }

        $tiposRequeridos = ServicioEvolucion::tiposRequeridos($destino);

        if (! $tiposRequeridos->contiene($tipo)) {
            return response()->json([
                'error' => $esVersion
                    ? 'Ese tipo no es necesario para la forma'
                    : 'Ese tipo no es necesario para la evolución',
            ], 422);
        }

        $userId = (int) auth()->id();

        $cantidadSolicitada = (int) ($data['cantidad'] ?? 1);

        // El lock y la escritura de EXP comparten transacción: el caramelo no
        // puede consumirse si la EXP no se persiste, y dos peticiones
        // concurrentes no pueden pisarse el JSON `exp`.
        $resultado = DB::transaction(function () use ($reclutado, $destino, $tipo, $userId, $cantidadSolicitada, $esVersion): array {
            $bloqueado = Reclutado::query()
                ->whereKey($reclutado->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($bloqueado->pokemon_id !== $reclutado->pokemon_id) {
                return ['error' => 'La evolución ya se ha aplicado.'];
            }

            $umbral = $esVersion
                ? ServicioEvolucion::umbralParaNivelObjetivo($bloqueado, ServicioVersiones::nivelDesbloqueo())
                : ServicioEvolucion::umbralParaEvolucion($bloqueado, $destino);

            $faltante = max(0, $umbral - $bloqueado->exp->expTipo($tipo));

            if ($faltante <= 0) {
                return ['error' => "Ya no necesita más caramelos de tipo {$tipo}"];
            }

            // Cada caramelo aporta una cantidad fija: no gastar más de lo que
            // falta para el umbral.
            $cantidad = min($cantidadSolicitada, (int) ceil($faltante / ReglasCaramelo::EXP_POR_CARAMELO));

            // Lock de la fila del inventario del jugador: evita doble consumo
            // concurrente (decrement atómico tras el chequeo de stock).
            $caramelo = PlayerInventory::where('user_id', $userId)
                ->where('item_key', ItemCatalogo::keyTipo($tipo))
                ->lockForUpdate()
                ->first();

            if ($caramelo === null || $caramelo->cantidad <= 0) {
                return ['error' => "No hay caramelos de tipo {$tipo}"];
            }

            $consumidos = min($cantidad, $caramelo->cantidad);
            $caramelo->decrement('cantidad', $consumidos);

            $bloqueado->exp = $bloqueado->exp->sumarExpTipo(
                $tipo,
                ReglasCaramelo::EXP_POR_CARAMELO * $consumidos,
            );
            $bloqueado->save();

            return [
                'reclutado' => $bloqueado,
                'caramelo' => $caramelo,
                'consumidos' => $consumidos,
            ];
        });

        if (isset($resultado['error'])) {
            return response()->json(['error' => $resultado['error']], 422);
        }

        $reclutadoActualizado = $resultado['reclutado'];

        $puedeDesbloquear = null;

        if ($esVersion) {
            $requisitos = ServicioEvolucion::requisitosParaNivel(
                $reclutadoActualizado,
                $destino,
                ServicioVersiones::nivelDesbloqueo(),
                $userId,
            );
            $puedeDesbloquear = $requisitos->isEmpty() || $requisitos->cumple();
        }

        return response()->json([
            'success' => true,
            'actual' => $reclutadoActualizado->exp->expTipo($tipo),
            'caramelos_disponibles' => $resultado['caramelo']->cantidad,
            'cantidad_consumida' => $resultado['consumidos'],
            'exp_ganada' => ReglasCaramelo::EXP_POR_CARAMELO * $resultado['consumidos'],
            'puede_evolucionar' => ! $esVersion
                && ServicioEvolucion::puedeEvolucionarPara($reclutadoActualizado, $userId, $destino),
            'puede_desbloquear' => $puedeDesbloquear,
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
                ->whereKey($reclutado->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $evsActuales = $bloqueado->evs;
            $valorActual = $evsActuales->valorDe($stat);

            if ($valorActual >= ReglasEv::TOPE) {
                return ['error' => 'Ese stat ya está al máximo ('.ReglasEv::TOPE.')'];
            }

            $maxPorEv = ReglasEv::caramelosHastaTope($valorActual, $cantidadSolicitada);
            $cantidad = $maxPorEv;

            if ($cantidad <= 0) {
                return ['error' => 'Ese stat ya está al máximo ('.ReglasEv::TOPE.')'];
            }

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

        $userId = (int) auth()->id();
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

        $tipos = ServicioEvolucion::tiposRequeridos($destino);

        // Lock + revalidación dentro de la transacción: dos peticiones
        // concurrentes no pueden consumir la EXP dos veces ni aplicar dos
        // evoluciones distintas sobre el mismo reclutado.
        DB::transaction(function () use ($reclutado, $destino, $tipos, $userId): void {
            $bloqueado = Reclutado::query()
                ->whereKey($reclutado->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($bloqueado->pokemon_id !== $reclutado->pokemon_id) {
                throw new ViolacionReglaNegocio('La evolución ya se ha aplicado.');
            }

            $umbralActual = ServicioEvolucion::umbralParaEvolucion($bloqueado, $destino);

            if (! ServicioEvolucion::puedeEvolucionarPara($bloqueado, $userId, $destino)) {
                throw new ViolacionReglaNegocio('No cumple los requisitos');
            }

            ServicioEvolucion::consumirExpTipo($bloqueado, $tipos, $umbralActual);

            $origenId = $bloqueado->pokemon_id;
            $bloqueado->update(['pokemon_id' => $destino->id]);

            if (ServicioVersiones::esVersionable($origenId) || ServicioVersiones::esVersionable($destino->id)) {
                ServicioVersiones::registrarForma($bloqueado, $origenId);
                ServicioVersiones::registrarForma($bloqueado, $destino->id);
                ServicioVersiones::asegurarOriginal($bloqueado);
            }
        });

        ActualizarPokedexJob::dispatch($userId, $destino->id, 'RECLUTADO');

        $reclutado->refresh()->load('pokemon.types', 'pokemon.stats', 'pokemon.abilities', 'pokemon.moves');

        return response()->json([
            'success' => true,
            'pokemon_id' => $destino->id,
            'reclutado' => ReclutadoSerializer::serializar(
                $reclutado,
                auth()->user()?->nivel(),
                piezas: $this->fabricaPiezas->paraListado($userId, [$reclutado]),
            ),
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
     * Formas/versiones disponibles del reclutado con su estado de desbloqueo.
     * Solo los grupos de `config/pokemon_versions.php` son versionables.
     */
    public function versiones(Request $request, Reclutado $reclutado): JsonResponse
    {
        $userId = (int) $request->user()?->getAuthIdentifier();
        $nivelObjetivo = ServicioVersiones::nivelDesbloqueo();
        $reclutado->loadMissing('pokemon');

        if ($reclutado->pokemon === null) {
            return response()->json(['versiones' => [], 'nivel_objetivo' => $nivelObjetivo]);
        }

        $ids = ServicioVersiones::grupoDe($reclutado->pokemon_id);
        if (count($ids) <= 1) {
            return response()->json(['versiones' => [], 'nivel_objetivo' => $nivelObjetivo]);
        }

        $originalId = ServicioVersiones::asegurarOriginal($reclutado);
        $versiones = [];

        foreach ($ids as $id) {
            $pokemon = Pokemon::find($id);
            if (! $pokemon instanceof Pokemon) {
                continue;
            }

            $desbloqueada = ServicioVersiones::estaDesbloqueada($reclutado, $id);
            $requisitos = null;
            $puedeDesbloquear = true;

            if (! $desbloqueada) {
                $coleccion = ServicioEvolucion::requisitosParaNivel(
                    $reclutado,
                    $pokemon,
                    $nivelObjetivo,
                    $userId,
                );

                $requisitos = $this->requisitosAVista($coleccion);
                $puedeDesbloquear = $coleccion->isEmpty() || $coleccion->cumple();
            }

            $versiones[] = [
                'pokemon_id' => $id,
                'nombre' => ServicioVersiones::nombreVisible($pokemon),
                'imagen' => "/images/iconos_webp/{$id}.webp",
                'actual' => $id === $reclutado->pokemon_id,
                'original' => $id === $originalId,
                'desbloqueada' => $desbloqueada,
                'puede_desbloquear' => $puedeDesbloquear,
                'requisitos' => $requisitos,
            ];
        }

        return response()->json([
            'versiones' => $versiones,
            'nivel_objetivo' => $nivelObjetivo,
        ]);
    }

    /**
     * Desbloquea una forma alternativa pagando la exp de tipo equivalente a
     * subir al Pokémon al nivel de desbloqueo (55). Es permanente para ese
     * reclutado: después podrá cambiar gratis.
     */
    public function desbloquearVersion(Request $request, Reclutado $reclutado, int $pokemon): JsonResponse
    {
        if ($reclutado->estaEnExploracion()) {
            return response()->json(['error' => 'No se puede cambiar de forma a un Pokémon en exploración'], 422);
        }

        $reclutado->loadMissing('pokemon');
        $actual = $reclutado->pokemon;
        $destino = Pokemon::find($pokemon);

        if ($actual === null || $destino === null) {
            return response()->json(['error' => 'Forma no válida'], 422);
        }

        if (! ServicioVersiones::sonDelMismoGrupo($actual->id, $destino->id)) {
            return response()->json(['error' => 'Esa forma no pertenece a la misma especie'], 422);
        }

        $originalId = ServicioVersiones::asegurarOriginal($reclutado);

        if ($destino->id === $actual->id
            || $destino->id === $originalId
            || ServicioVersiones::estaDesbloqueada($reclutado, $destino->id)
        ) {
            return response()->json(['success' => true]);
        }

        $userId = (int) $request->user()?->getAuthIdentifier();
        $nivelObjetivo = ServicioVersiones::nivelDesbloqueo();
        $requisitos = ServicioEvolucion::requisitosParaNivel($reclutado, $destino, $nivelObjetivo, $userId);

        if (! $requisitos->isEmpty() && ! $requisitos->cumple()) {
            return response()->json([
                'error' => 'No cumples los requisitos para desbloquear esta forma',
                'requisitos' => $this->requisitosAVista($requisitos),
            ], 422);
        }

        $tipos = ServicioEvolucion::tiposRequeridos($destino);

        DB::transaction(function () use ($reclutado, $destino, $tipos): void {
            $bloqueado = Reclutado::query()
                ->whereKey($reclutado->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($bloqueado->pokemon_id !== $reclutado->pokemon_id) {
                throw new ViolacionReglaNegocio('La versión ya ha cambiado.');
            }

            $umbral = ServicioEvolucion::umbralParaNivelObjetivo(
                $bloqueado,
                ServicioVersiones::nivelDesbloqueo(),
            );

            ServicioEvolucion::consumirExpTipo($bloqueado, $tipos, $umbral);

            ServicioVersiones::registrarForma($bloqueado, $bloqueado->pokemon_id);
            ServicioVersiones::registrarForma($bloqueado, $destino->id);
        });

        $reclutado->refresh()->load('pokemon.types', 'pokemon.stats', 'pokemon.abilities', 'pokemon.moves');

        $nivelUsuario = $request->user() instanceof User ? $request->user()->nivel() : null;

        return response()->json([
            'success' => true,
            'reclutado' => ReclutadoSerializer::serializar(
                $reclutado,
                $nivelUsuario,
                piezas: $this->fabricaPiezas->paraListado($userId, [$reclutado]),
            ),
        ]);
    }

    /**
     * Cambia a una forma ya desbloqueada (o a la original/actual). Gratis.
     */
    public function cambiarVersion(Request $request, Reclutado $reclutado): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'pokemon_id' => ['required', 'integer', 'exists:pokemon,id'],
        ])->validate();

        if ($reclutado->estaEnExploracion()) {
            return response()->json(['error' => 'No se puede cambiar de forma a un Pokémon en exploración'], 422);
        }

        $reclutado->loadMissing('pokemon');
        $actual = $reclutado->pokemon;
        $destino = Pokemon::findOrFail((int) $data['pokemon_id']);

        if ($actual === null) {
            return response()->json(['error' => 'Forma no válida'], 422);
        }

        if (! ServicioVersiones::sonDelMismoGrupo($actual->id, $destino->id)) {
            return response()->json(['error' => 'Esa forma no pertenece a la misma especie'], 422);
        }

        $originalId = ServicioVersiones::asegurarOriginal($reclutado);

        if ($destino->id !== $originalId
            && ! ServicioVersiones::estaDesbloqueada($reclutado, $destino->id)
        ) {
            return response()->json(['error' => 'Desbloquea esta forma antes de cambiarla'], 422);
        }

        if ($destino->id === $actual->id) {
            return response()->json(['success' => true]);
        }

        $userId = (int) $request->user()?->getAuthIdentifier();

        DB::transaction(function () use ($reclutado, $destino): void {
            $bloqueado = Reclutado::query()
                ->whereKey($reclutado->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($bloqueado->pokemon_id !== $reclutado->pokemon_id) {
                throw new ViolacionReglaNegocio('La versión ya ha cambiado.');
            }

            $destino->loadMissing('abilities');
            $habilidades = $destino->abilities->pluck('id');

            ServicioVersiones::registrarForma($bloqueado, $bloqueado->pokemon_id);
            ServicioVersiones::registrarForma($bloqueado, $destino->id);

            $bloqueado->pokemon_id = $destino->id;

            if ($bloqueado->habilidad_activa_id !== null
                && ! $habilidades->contains($bloqueado->habilidad_activa_id)
            ) {
                $bloqueado->habilidad_activa_id = $habilidades->first();
            }

            $bloqueado->save();
        });

        ActualizarPokedexJob::dispatch($userId, $destino->id, 'RECLUTADO');

        $reclutado->refresh()->load('pokemon.types', 'pokemon.stats', 'pokemon.abilities', 'pokemon.moves');

        $nivelUsuario = $request->user() instanceof User ? $request->user()->nivel() : null;

        return response()->json([
            'success' => true,
            'pokemon_id' => $destino->id,
            'reclutado' => ReclutadoSerializer::serializar(
                $reclutado,
                $nivelUsuario,
                piezas: $this->fabricaPiezas->paraListado($userId, [$reclutado]),
            ),
        ]);
    }

    /**
     * Serializa los requisitos de exp de tipo para la vista sin usar el
     * toArray() deprecado de la colección.
     *
     * @return list<array{tipo: string, slug: string, necesario: int, actual: int, caramelosDisponibles: int}>
     */
    private function requisitosAVista(RequisitoEvolucionCollection $coleccion): array
    {
        $resultado = [];

        foreach ($coleccion as $requisito) {
            if (! $requisito instanceof RequisitoEvolucion) {
                continue;
            }

            $resultado[] = [
                'tipo' => $requisito->tipo,
                'slug' => $requisito->slug,
                'necesario' => $requisito->necesario,
                'actual' => $requisito->actual,
                'caramelosDisponibles' => $requisito->caramelosDisponibles,
            ];
        }

        return $resultado;
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
     * su especie. Las habilidades no se seleccionan: todas las de la especie
     * están activas en combate.
     */
    public function actualizarLoadout(Request $request, Reclutado $reclutado): JsonResponse
    {
        if ($reclutado->estaEnExploracion()) {
            return response()->json([
                'error' => 'No se puede modificar el loadout de un Pokémon en exploración',
            ], 422);
        }

        $data = $request->validate([
            'movimientos' => 'sometimes|array|max:6',
            'movimientos.*' => 'string',
        ]);

        $reclutado->loadMissing('pokemon.abilities', 'pokemon.moves');
        $pokemon = $reclutado->pokemon;

        if ($pokemon === null) {
            return response()->json(['error' => 'El reclutado no tiene Pokémon asociado'], 422);
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
            'movimientos' => $slugs,
        ]);

        $reclutado->load('pokemon.abilities', 'pokemon.moves', 'pokemon.types', 'pokemon.stats');

        return response()->json([
            'success' => true,
            'reclutado' => ReclutadoSerializer::serializar(
                $reclutado,
                auth()->user()?->nivel(),
                piezas: $this->fabricaPiezas->paraListado($userId, [$reclutado]),
            ),
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

        // Mutex por usuario + transacción: el conteo y la inserción no pueden
        // intercalarse entre dos peticiones del mismo jugador.
        $resultado = DB::transaction(function () use ($userId, $reclutado, $habitatId): array {
            User::query()->whereKey($userId)->lockForUpdate()->first();

            $max = 6;
            $esFavorito = Favorito::esFavorito($userId, $reclutado->id, $habitatId);

            if (! $esFavorito) {
                $actual = $habitatId === null
                    ? Favorito::countGlobales($userId)
                    : Favorito::countParaHabitat($userId, $habitatId);

                if ($actual >= $max) {
                    $mensaje = $habitatId === null
                        ? 'Máximo 6 favoritos globales.'
                        : 'Máximo 6 favoritos para este hábitat.';

                    return ['error' => $mensaje];
                }
            }

            $favorito = Favorito::toggle($userId, $reclutado->id, $habitatId);

            $count = $habitatId === null
                ? Favorito::countGlobales($userId)
                : Favorito::countParaHabitat($userId, $habitatId);

            return ['favorito' => $favorito, 'count' => $count];
        });

        if (isset($resultado['error'])) {
            return response()->json(['message' => $resultado['error']], 422);
        }

        return response()->json($resultado);
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

        $reclutadosFavoritos = $query
            ->withoutGlobalScope('belongsToUser')
            ->where('user_id', $userId)
            ->get()
            ->map(fn (Favorito $favorito): Reclutado => $favorito->reclutado);

        $reclutados = ReclutadoSerializer::serializarColeccion(
            $reclutadosFavoritos,
            auth()->user()?->nivel(),
            incluirMovimientosDisponibles: false,
            piezas: $this->fabricaPiezas->paraListado($userId, $reclutadosFavoritos),
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
    public function listarTodos(Request $request): JsonResponse
    {
        $reclutadosQuery = Reclutado::with([
            'pokemon.types',
            'pokemon.stats',
            'pokemon.abilities',
            'pokemon.moves',
        ])->get();

        $reclutados = ReclutadoSerializer::serializarColeccion(
            $reclutadosQuery,
            auth()->user()?->nivel(),
            incluirMovimientosDisponibles: false,
            piezas: $this->fabricaPiezas->paraListado((int) $request->user()?->getKey(), $reclutadosQuery),
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
            'behavior' => ['required', 'string', Rule::enum(RolExploracion::class)],
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
