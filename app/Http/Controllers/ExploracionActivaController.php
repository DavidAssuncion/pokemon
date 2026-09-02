<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\Reclutado;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Src\Exploraciones\App\ProcesarExploracionCommand;
use Src\Exploraciones\Domain\CapacidadesStats;
use Src\Exploraciones\Domain\EvaluadorExploracion;
use Src\Habitats\App\ValidadorExploracion;
use Src\Shared\Bus\CommandBus;
use Src\Shared\Domain\NivelHelper;

class ExploracionActivaController extends Controller
{
    /**
     * Datos de stat por id (índice = stat id): nombre en español alineado con
     * el fallback JS de la vista (statName) — StatEnum::label() devuelve
     * 'PS (HP)' para HP y divergiría — y slug usado por el frontend para los
     * iconos de los caramelos EV.
     *
     * @var array<int, array{nombre: string, slug: string}>
     */
    private const STATS = [
        1 => ['nombre' => 'PS', 'slug' => 'hp'],
        2 => ['nombre' => 'Ataque', 'slug' => 'atk'],
        3 => ['nombre' => 'Defensa', 'slug' => 'def'],
        4 => ['nombre' => 'Ataque Especial', 'slug' => 'atksp'],
        5 => ['nombre' => 'Defensa Especial', 'slug' => 'defsp'],
        6 => ['nombre' => 'Velocidad', 'slug' => 'spd'],
    ];

    public function __construct(
        private readonly ValidadorExploracion $validadorExploracion,
        private readonly CommandBus $bus,
    ) {
    }

    public function index(): View
    {
        $activas = ExploracionActiva::whereNull('regreso')
            ->with('reclutado', 'habitat')
            ->get();

        $terminadas = ExploracionActiva::whereNotNull('regreso')
            ->with('reclutado', 'habitat')
            ->get();

        $nombres = $this->nombresPokemon($activas, $terminadas);

        return view('exploraciones.index', [
            'activas' => $activas
                ->map(fn (ExploracionActiva $exp) => $this->toActiva($exp, $nombres))
                ->all(),
            'terminadas' => $terminadas
                ->map(fn (ExploracionActiva $exp) => $this->toTerminada($exp, $nombres))
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
        ]);

        $usuario = Auth::user();
        $reclutado = Reclutado::with('pokemon.stats', 'pokemon.types')->findOrFail((int) $data['reclutado_id']);
        $habitat = Habitat::find((int) $data['habitat_id']);
        $nivel = (int) $data['level'];
        $minLvl = $this->minLvlDelHabitat($habitat, $nivel);

        $capacidades = CapacidadesStats::desdeReclutado($reclutado, $usuario)->todas();

        // Riesgo simple (exploración individual): combate vs dificultad base
        // normal del hábitat (30 + peligro×5).
        $peligro = $habitat?->peligro ?? 1;
        $dificultad = EvaluadorExploracion::dificultad('normal', max(1, $peligro));
        $riesgo = $capacidades['combate'] >= $dificultad
            ? 'Bajo'
            : ($capacidades['combate'] >= $dificultad - EvaluadorExploracion::MARGEN_EXITO_CON_COSTE ? 'Medio' : 'Alto');

        return response()->json([
            'capacidades' => $capacidades,
            'nivel_jugador' => $usuario->nivel(),
            'nivel_pokemon' => NivelHelper::nivelDesdeExperiencia($reclutado->exp->total()),
            'min_lvl' => $minLvl,
            'peligro' => $peligro,
            'riesgo' => $riesgo,
        ]);
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

        if (! $this->validadorExploracion->equipoDelReclutadoDisponible((int) $data['reclutado_id'])) {
            return 'El equipo del reclutado está en una exploración activa.';
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

    /**
     * @param  Collection<int, ExploracionActiva>  $activas
     * @param  Collection<int, ExploracionActiva>  $terminadas
     * @return array<array-key, string>
     */
    private function nombresPokemon(Collection $activas, Collection $terminadas): array
    {
        $ids = [];
        foreach ($activas as $exp) {
            foreach ($this->eventosDe($exp)->get('bitacora', []) as $evento) {
                foreach ($this->idsDeEvento($evento) as $id) {
                    $ids[] = $id;
                }
            }
        }

        foreach ($terminadas as $exp) {
            $resultado = $this->eventosDe($exp)->get('resultado', []);
            foreach ($resultado['capturados'] ?? [] as $capturado) {
                $ids[] = (int) $capturado['pokemon_id'];
            }
        }

        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));

        return $ids === []
            ? []
            : Pokemon::whereIn('id', $ids)->pluck('name', 'id')->all();
    }

    /**
     * Eventos de la expedición como Collection (D11: cast 'collection').
     * null → colección vacía (exploración recién creada sin eventos).
     *
     * @return BaseCollection<array-key, mixed>
     */
    private function eventosDe(ExploracionActiva $exp): BaseCollection
    {
        return $exp->eventos ?? collect();
    }

    /**
     * @param  array<string, mixed>  $evento
     * @return list<int>
     */
    private function idsDeEvento(array $evento): array
    {
        if (isset($evento['pokemon_ids']) && is_array($evento['pokemon_ids'])) {
            return array_values(array_map('intval', $evento['pokemon_ids']));
        }

        if (isset($evento['pokemon_id'])) {
            return [(int) $evento['pokemon_id']];
        }

        return [];
    }

    /**
     * @param  array<array-key, string>  $nombres
     * @return array<string, mixed>
     */
    private function toActiva(ExploracionActiva $exp, array $nombres): array
    {
        $inicio = $exp->inicio_exploracion?->copy() ?? $exp->created_at?->copy() ?? now();
        $fin = $this->finExploracion($exp, $inicio);
        $inicioVuelta = $this->inicioVuelta($inicio, $fin);
        $ahora = now();

        $estado = $inicioVuelta !== null && ! $ahora->lessThan($inicioVuelta)
            ? 'volviendo'
            : 'explorando';

        $progreso = 0;
        if ($fin !== null && $fin->greaterThan($inicio)) {
            $total = (int) abs($fin->diffInSeconds($inicio));
            $transcurrido = (int) abs($ahora->diffInSeconds($inicio));
            $progreso = max(0, min(100, (int) round(($transcurrido / $total) * 100)));
        }

        $bitacora = [];
        foreach ($this->eventosDe($exp)->get('bitacora', []) as $evento) {
            $bitacora[] = $this->transformarEvento($evento, $nombres);
        }

        $reclutado = $exp->reclutado;
        $habitat = $exp->habitat;

        return [
            'id' => $exp->id,
            'equipo' => $reclutado !== null ? $reclutado->nombre : 'Sin reclutado',
            'reclutado' => $reclutado !== null ? $reclutado->nombre : null,
            'habitat' => $habitat !== null ? $habitat->name : 'Sin hábitat',
            'habitat_id' => $exp->habitat_id,
            'nivel' => $exp->nivel,
            'min_lvl' => $this->minLvlDelHabitat($habitat, $exp->nivel),
            'indefinido' => $exp->indefinido,
            'duracion_horas' => $exp->duracion_horas,
            'inicio' => $inicio->toIso8601String(),
            'inicio_vuelta' => $inicioVuelta?->toIso8601String(),
            'fin' => $fin?->toIso8601String(),
            'estado' => $estado,
            'progreso' => $progreso,
            'tiempo_perdido' => (int) $this->eventosDe($exp)->get('tiempo_perdido', 0),
            'bitacora' => $bitacora,
        ];
    }

    /**
     * @param  array<array-key, string>  $nombres
     * @return array<string, mixed>
     */
    private function toTerminada(ExploracionActiva $exp, array $nombres): array
    {
        /** @var array<string, mixed> $resultado */
        $resultado = $this->eventosDe($exp)->get('resultado', []);

        $capturados = [];
        foreach ($resultado['capturados'] ?? [] as $capturado) {
            $id = (int) $capturado['pokemon_id'];
            $capturados[] = [
                'pokemon_id' => $id,
                'nombre' => $capturado['nombre'] ?? $nombres[$id] ?? null,
                'cantidad' => (int) ($capturado['cantidad'] ?? 0),
            ];
        }

        $caramelosFamilia = [];
        foreach ($resultado['caramelos_familia'] ?? [] as $caramelo) {
            $caramelosFamilia[] = [
                'evolution_chain_id' => (int) ($caramelo['evolution_chain_id'] ?? 0),
                'nombre' => $caramelo['nombre'] ?? null,
                'pokemon_id' => $caramelo['pokemon_id'] ?? null,
                'cantidad' => (int) ($caramelo['cantidad'] ?? 0),
            ];
        }

        $caramelosEv = [];
        foreach ($resultado['caramelos_ev'] ?? [] as $caramelo) {
            $stat = (int) ($caramelo['stat'] ?? 0);
            $statInfo = self::STATS[$stat] ?? null;
            $caramelosEv[] = [
                'stat' => $stat,
                'stat_nombre' => $statInfo['nombre'] ?? null,
                'stat_slug' => $statInfo['slug'] ?? null,
                'cantidad' => (int) ($caramelo['cantidad'] ?? 0),
            ];
        }

        $caramelosTipo = [];
        foreach ($resultado['caramelos_tipo'] ?? [] as $caramelo) {
            $caramelosTipo[] = [
                'tipo' => $caramelo['tipo'] ?? null,
                'slug' => $caramelo['slug'] ?? null,
                'cantidad' => (int) ($caramelo['cantidad'] ?? 0),
            ];
        }

        $reclutado = $exp->reclutado;
        $habitat = $exp->habitat;

        return [
            'id' => $exp->id,
            'equipo' => $reclutado !== null ? $reclutado->nombre : 'Sin reclutado',
            'reclutado' => $reclutado !== null ? $reclutado->nombre : null,
            'habitat' => $habitat !== null ? $habitat->name : 'Sin hábitat',
            'nivel' => $exp->nivel,
            'min_lvl' => $this->minLvlDelHabitat($habitat, $exp->nivel),
            'resultado' => [
                'capturados' => $capturados,
                'caramelos_familia' => $caramelosFamilia,
                'caramelos_ev' => $caramelosEv,
                'caramelos_tipo' => $caramelosTipo,
                'exp' => (int) ($resultado['exp'] ?? 0),
                'resultado' => (string) ($resultado['resultado'] ?? 'exito'),
                'duration_real' => (int) ($resultado['duration_real'] ?? 0),
                'tiempo_perdido' => (int) ($resultado['tiempo_perdido'] ?? 0),
                'incidentes' => $resultado['incidentes'] ?? [
                    'encuentros' => 0,
                    'victorias' => 0,
                    'huidas' => 0,
                    'emboscadas' => 0,
                    'contratiempos' => 0,
                ],
            ],
            'derrotados' => $this->eventosDe($exp)->get('derrotados', []),
        ];
    }

    /**
     * @param  array<string, mixed>  $evento
     * @param  array<array-key, string>  $nombres
     * @return array<string, mixed>
     */
    private function transformarEvento(array $evento, array $nombres): array
    {
        $tipo = $evento['tipo'] ?? 'desconocido';

        if ($tipo === 'caramelo_ev') {
            $statInfo = self::STATS[(int) ($evento['stat'] ?? 0)] ?? null;
            $evento['stat_nombre'] = $statInfo['nombre'] ?? null;
            $evento['stat_slug'] = $statInfo['slug'] ?? null;
        } elseif (isset($evento['pokemon_id'])) {
            $evento['nombre'] = $nombres[(int) $evento['pokemon_id']] ?? null;
        } elseif (isset($evento['pokemon_ids'])) {
            $ids = array_values(array_filter(
                array_map('intval', (array) $evento['pokemon_ids']),
                static fn (int $id): bool => $id > 0
            ));
            $evento['nombre'] = $ids === [] ? null : ($nombres[$ids[0]] ?? null);
        }

        return $evento;
    }

    /**
     * Nivel mínimo de jugador requerido por el hábitat para el nivel de
     * exploración dado (null = sin restricción). Lo consume el badge
     * "Requiere Nv X" de la vista de exploraciones.
     */
    private function minLvlDelHabitat(?Habitat $habitat, int $nivel): ?int
    {
        $minLvl = $habitat?->getAttribute('min_lvl_'.$nivel);

        return $minLvl !== null ? (int) $minLvl : null;
    }

    private function finExploracion(ExploracionActiva $exp, CarbonInterface $inicio): ?CarbonInterface
    {
        if ($exp->hora_limite !== null) {
            return Carbon::today()->setTimeFromTimeString($exp->hora_limite);
        }

        if ($exp->duracion_horas !== null) {
            return $inicio->copy()->addHours($exp->duracion_horas);
        }

        return null;
    }

    private function inicioVuelta(CarbonInterface $inicio, ?CarbonInterface $fin): ?CarbonInterface
    {
        if ($fin === null) {
            return null;
        }

        return $fin->copy()->subMinutes(intdiv((int) abs($fin->diffInMinutes($inicio)), 4));
    }
}
