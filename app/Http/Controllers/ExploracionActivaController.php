<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use App\Models\Reclutado;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Src\Exploraciones\App\FabricaCapacidadesStats;
use Src\Exploraciones\App\ProcesarExploracionCommand;
use Src\Exploraciones\Domain\EstimadorRecompensasExploracion;
use Src\Exploraciones\Domain\EvaluadorExploracion;
use Src\Exploraciones\Domain\RolExploracion;
use Src\Exploraciones\Presentation\PresentadorExploraciones;
use Src\Habitats\App\ValidadorExploracion;
use Src\Shared\Bus\CommandBus;
use Src\Shared\Domain\NivelHelper;
use Src\Shared\Tipos\TipoPokemon;

class ExploracionActivaController extends Controller
{
    public function __construct(
        private readonly ValidadorExploracion $validadorExploracion,
        private readonly CommandBus $bus,
        private readonly EstimadorRecompensasExploracion $estimador,
        private readonly PresentadorExploraciones $presentador,
    ) {
    }

    public function index(): View
    {
        $activas = ExploracionActiva::whereNull('regreso')
            ->with('reclutado', 'habitat')
            ->get();

        // RF-A: excluye canceladas de "Resultados por revisar".
        $terminadas = ExploracionActiva::whereNotNull('regreso')
            ->with('reclutado', 'habitat')
            ->get()
            ->reject(fn (ExploracionActiva $exp): bool => $this->estaCancelada($exp))
            ->values();

        $nombres = $this->presentador->nombresPokemon($activas, $terminadas);

        return view('exploraciones.index', [
            'activas' => $activas
                ->map(fn (ExploracionActiva $exp) => $this->presentador->toActiva($exp, $nombres))
                ->all(),
            'terminadas' => $terminadas
                ->map(fn (ExploracionActiva $exp) => $this->presentador->toTerminada($exp, $nombres))
                ->all(),
        ]);
    }

    /**
     * RF-11: preview de riesgo antes de enviar al reclutado (D12). Anti-IDOR:
     * el reclutado debe pertenecer al usuario autenticado (regla exists con
     * user_id). Contrato aditivo individual: capacidades por stats + nivel +
     * min_lvl + peligro + riesgo simple.
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reclutado_id' => ['required', 'integer', Rule::exists('reclutados', 'id')->where('user_id', Auth::id())],
            'habitat_id' => 'required|integer|exists:habitats,id',
            'level' => 'required|integer|min:1|max:3',
            'duracion_horas' => 'nullable|integer|min:1|max:72',
            'duration_hours' => 'nullable|integer|min:1|max:72',
            'return_time' => 'nullable|date_format:H:i',
        ]);

        $usuario = Auth::user();
        $reclutado = Reclutado::with('pokemon.stats', 'pokemon.types')->findOrFail((int) $data['reclutado_id']);
        $habitat = Habitat::find((int) $data['habitat_id']);
        $nivel = (int) $data['level'];
        $minLvl = $this->presentador->minLvlDelHabitat($habitat, $nivel);

        $capacidades = FabricaCapacidadesStats::desdeReclutado($reclutado, $usuario);

        // Riesgo simple (exploración individual): combate vs dificultad base
        // normal del hábitat (30 + peligro×5).
        $peligro = $habitat?->peligro ?? 1;
        $dificultad = EvaluadorExploracion::dificultad('normal', max(1, $peligro));
        $combate = $capacidades->combate();
        $riesgo = $combate >= $dificultad
            ? 'Bajo'
            : ($combate >= $dificultad - EvaluadorExploracion::MARGEN_EXITO_CON_COSTE ? 'Medio' : 'Alto');

        // RF-D: recompensas esperadas (estimador puro) sobre el pool del hábitat.
        $porHoras = $this->porHorasDe($data);
        $pool = $this->poolParaEstimador($habitat, $nivel);
        $nivelSalvaje = max(1, $minLvl ?? $dificultad);
        $estimacion = $this->estimador->estimar(
            pool: $pool,
            porHoras: $porHoras,
            capacidades: $capacidades,
            dificultad: $dificultad,
            nivelSalvaje: $nivelSalvaje,
            cadenas: $this->cadenasDelPool($pool),
        );

        return response()->json([
            'capacidades' => $capacidades->todas(),
            'nivel_jugador' => $usuario->nivel(),
            'nivel_pokemon' => NivelHelper::nivelDesdeExperiencia($reclutado->exp->total()),
            'min_lvl' => $minLvl,
            'peligro' => $peligro,
            'riesgo' => $riesgo,
            'rol' => $reclutado->rol()?->value,
            'rol_sugerido' => RolExploracion::sugeridoPara($capacidades)->value,
            'recompensas_esperadas' => $estimacion,
        ]);
    }

    /**
     * Resuelve las horas previstas para la estimación de recompensas (RF-D):
     * duracion_horas si se indica, return_time → horas hasta hoy a esa hora
     * (mínimo 1), o 4 horas por defecto.
     *
     * @param  array<string, mixed>  $data
     */
    private function porHorasDe(array $data): int
    {
        $duracion = (int) ($data['duracion_horas'] ?? $data['duration_hours'] ?? 0);
        if ($duracion > 0) {
            return min(72, $duracion);
        }

        if (isset($data['return_time'])) {
            $limite = Carbon::today()->setTimeFromTimeString((string) $data['return_time']);
            $horas = max(1, (int) ceil(now()->diffInMinutes($limite, false) / 60));

            return min(72, $horas);
        }

        return 4;
    }

    /**
     * Pool de pokémon del hábitat-nivel con los campos extra que necesita el
     * EstimadorRecompensasExploracion (base_experience, evolution_chain_id,
     * species_id) sobre la misma base de ProcesarExploracionHandler.
     *
     * @param  int  $nivel
     * @return list<array{id:int, capture_rate:int, hatch:int|null, tipos:list<TipoPokemon>, stats:list<array{stat:int,effort:int}>, base_experience:int, evolution_chain_id:?int, species_id:int}>
     */
    private function poolParaEstimador(?Habitat $habitat, int $nivel): array
    {
        if ($habitat === null) {
            return [];
        }

        return $habitat->pokemon()
            ->wherePivot('level', $nivel)
            ->get()
            ->loadMissing('types', 'stats')
            ->map(fn (Pokemon $pokemon) => [
                'id' => $pokemon->id,
                'capture_rate' => $pokemon->capture_rate,
                'hatch' => $pokemon->hatch,
                'tipos' => $pokemon->types
                    ->map(fn (PokemonType $tipo): TipoPokemon => TipoPokemon::from($tipo->type->value))
                    ->values()
                    ->all(),
                'stats' => $pokemon->stats
                    ->filter(fn (PokemonStat $stat) => $stat->effort > 0)
                    ->map(fn (PokemonStat $stat) => [
                        'stat' => $stat->stat->value,
                        'effort' => $stat->effort,
                    ])
                    ->values()
                    ->all(),
                'base_experience' => $pokemon->base_experience,
                'evolution_chain_id' => $pokemon->evolution_chain_id,
                'species_id' => $pokemon->species_id,
            ])
            ->values()
            ->all();
    }

    /**
     * Mapa de especies por cadena evolutiva, para estimar la "fase" de familia.
     *
     * @param  list<array{id:int, capture_rate:int, hatch:int|null, tipos:list<TipoPokemon>, stats:list<array{stat:int,effort:int}>, base_experience:int, evolution_chain_id:?int, species_id:int}>  $pool
     * @return array<int, list<int>>
     */
    private function cadenasDelPool(array $pool): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(fn (array $p): ?int => $p['evolution_chain_id'] ?? null, $pool),
            static fn (?int $id): bool => $id !== null,
        )));

        if ($ids === []) {
            return [];
        }

        return Pokemon::whereIn('evolution_chain_id', $ids)
            ->get(['id', 'species_id', 'evolution_chain_id'])
            ->groupBy('evolution_chain_id')
            ->map(fn ($grupo) => $grupo->pluck('species_id')->unique()->values()->all())
            ->all();
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $errores = $this->validarYNuevaExploracion($request, $exploracion);
        if ($errores !== null) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $errores], 422);
            }

            return redirect()->back()->with('error', $errores)->withInput();
        }

        return redirect()->back()->with('success', 'Exploración iniciada correctamente.');
    }

    /**
     * POST /api/exploraciones/store-individual: inicia una exploración
     * individual (API JSON). Mismo flujo que store pero devuelve JSON.
     * Sin bayas (TODO).
     */
    public function storeIndividual(Request $request): JsonResponse
    {
        $errores = $this->validarYNuevaExploracion($request, $exploracion);
        if ($errores !== null) {
            return response()->json(['message' => $errores], 422);
        }

        return response()->json(['ok' => true, 'id' => $exploracion->id], 201);
    }

    /**
     * Valida los datos de una nueva exploración y la crea si es válida.
     * Devuelve null si todo ok, o un string de error si falla.
     * Mutación: $exploracion recibe la instancia creada si éxito.
     *
     * @param  ExploracionActiva|null  $exploracion  Output: exploración creada.
     */
    private function validarYNuevaExploracion(Request $request, ?ExploracionActiva &$exploracion = null): ?string
    {
        $data = $request->validate([
            'reclutado_id' => ['required', 'integer', Rule::exists('reclutados', 'id')->where('user_id', Auth::id())],
            'habitat_id' => 'required|exists:habitats,id',
            'level' => 'required|integer|min:1|max:3',
            'duracion_horas' => 'nullable|integer|min:1|max:72',
            'duration_hours' => 'nullable|integer|min:1|max:72',
            'return_time' => 'nullable|date_format:H:i',
            'indefinido' => 'nullable|boolean',
        ]);

        if (! $this->validadorExploracion->reclutadoDisponible((int) $data['reclutado_id'])) {
            return 'El reclutado ya está en una exploración activa.';
        }

        $usuario = $request->user();
        $habitat = Habitat::find((int) $data['habitat_id']);
        $minLvl = $habitat?->getAttribute('min_lvl_'.$data['level']);

        if ($minLvl !== null && $usuario instanceof User
            && ! $this->validadorExploracion->cumpleNivelMinimo($usuario->nivel(), (int) $minLvl)
        ) {
            return "Requiere nivel Nv {$minLvl} para explorar esta zona.";
        }

        $duracionHoras = (int) ($data['duracion_horas'] ?? $data['duration_hours'] ?? 0) ?: null;
        $indefinido = ($data['indefinido'] ?? false) || ($duracionHoras === null && ! isset($data['return_time']));

        $horaLimite = null;
        if (isset($data['return_time'])) {
            $horaLimite = Carbon::today()->setTimeFromTimeString($data['return_time']);
        }

        $exploracion = ExploracionActiva::create([
            'user_id' => $usuario?->id,
            'reclutado_id' => $data['reclutado_id'],
            'habitat_id' => $data['habitat_id'],
            'nivel' => $data['level'],
            'duracion_horas' => $duracionHoras,
            'hora_limite' => $horaLimite,
            'indefinido' => $indefinido,
            'inicio_exploracion' => null,
            'llegada_destino' => null,
            'regreso' => null,
        ]);

        return null;
    }

    /**
     * Cancela una exploración activa sin ejecutar el pipeline de recompensas:
     * marca `regreso = now()` y registra `eventos.cancelada = {motivo, timestamp}`.
     * No reparte recompensas ni avistados (RF-A). Solo el dueño; 422 si el
     * regreso ya estaba marcado por otra vía. Idempotente si ya fue cancelada.
     */
    public function cancelar(Request $request, ExploracionActiva $exploracion): RedirectResponse|JsonResponse
    {
        if ($exploracion->regreso !== null && ! $this->estaCancelada($exploracion)) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'La exploración ya no está activa.'], 422);
            }

            return redirect()->back()->with('error', 'La exploración ya no está activa.');
        }

        if ($exploracion->regreso === null) {
            $eventos = $exploracion->eventos ?? collect();
            $eventos->put('cancelada', [
                'motivo' => 'manual',
                'timestamp' => now()->toIso8601String(),
            ]);

            $exploracion->update(['regreso' => now(), 'eventos' => $eventos]);
        }

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->back()->with('success', 'Exploración cancelada correctamente.');
    }

    private function estaCancelada(ExploracionActiva $exploracion): bool
    {
        $eventos = $this->presentador->eventosDe($exploracion);

        return $eventos->get('cancelada') !== null;
    }

    /**
     * Finaliza la exploración manualmente (vuelta anticipada o indefinida)
     * y ejecuta el pipeline de recompensas.
     */
    public function recoger(Request $request, ExploracionActiva $exploracion): RedirectResponse|JsonResponse
    {
        $this->bus->dispatch(new ProcesarExploracionCommand($exploracion, forzarRegreso: true));

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->back()->with('success', 'Exploración recogida correctamente.');
    }

    /**
     * Elimina la exploración completada una vez revisados sus resultados.
     */
    public function cerrar(Request $request, ExploracionActiva $exploracion): RedirectResponse|JsonResponse
    {
        abort_unless($exploracion->regreso !== null, 404);

        $exploracion->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Resultados cerrados correctamente.');
    }
}
