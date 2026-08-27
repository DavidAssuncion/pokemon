<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ExploracionActiva;
use App\Models\Pokemon;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Src\Exploraciones\App\ProcesarExploracionService;
use Src\Habitats\App\ValidadorExploracion;

class ExploracionActivaController extends Controller
{
    /**
     * Nombres de stat en español, alineados con el fallback JS de la vista
     * (statName) — StatEnum::label() devuelve 'PS (HP)' para HP y divergiría.
     */
    private const STAT_NOMBRES = [
        1 => 'PS',
        2 => 'Ataque',
        3 => 'Defensa',
        4 => 'Ataque Especial',
        5 => 'Defensa Especial',
        6 => 'Velocidad',
    ];

    public function __construct(
        private readonly ValidadorExploracion $validadorExploracion,
        private readonly ProcesarExploracionService $procesarExploracion,
    ) {
    }

    public function index(): View
    {
        $activas = ExploracionActiva::whereNull('regreso')
            ->with('team', 'habitat')
            ->get();

        $terminadas = ExploracionActiva::whereNotNull('regreso')
            ->with('team', 'habitat')
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

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'team_id' => 'required|exists:teams,id',
            'habitat_id' => 'required|exists:habitats,id',
            'level' => 'required|integer|min:1|max:3',
            'duration_hours' => 'nullable|integer|min:1|max:72',
            'return_time' => 'nullable|date_format:H:i',
            'indefinido' => 'nullable|boolean',
        ]);

        // Check team is not already in exploration
        if (! $this->validadorExploracion->equipoDisponible((int) $data['team_id'])) {
            return redirect()->back()->with('error', 'El equipo ya está en una exploración activa.');
        }

        $indefinido = ($data['indefinido'] ?? false) || (! isset($data['duration_hours']) && ! isset($data['return_time']));

        $horaLimite = null;
        if (isset($data['return_time'])) {
            $horaLimite = Carbon::today()->setTimeFromTimeString($data['return_time']);
        }

        ExploracionActiva::create([
            'equipo_id' => $data['team_id'],
            'habitat_id' => $data['habitat_id'],
            'nivel' => $data['level'],
            'duracion_horas' => $data['duration_hours'] ?? null,
            'hora_limite' => $horaLimite,
            'indefinido' => $indefinido,
            'inicio_exploracion' => null,
            'llegada_destino' => null,
            'regreso' => null,
        ]);

        return redirect()->back()->with('success', 'Exploración iniciada correctamente.');
    }

    /**
     * Finaliza la exploración manualmente (vuelta anticipada o indefinida)
     * y ejecuta el pipeline de recompensas.
     */
    public function recoger(Request $request, ExploracionActiva $exploracion): RedirectResponse|JsonResponse
    {
        $this->procesarExploracion->procesar($exploracion, forzarRegreso: true);

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
            foreach ($exp->eventos['bitacora'] ?? [] as $evento) {
                if (isset($evento['pokemon_id'])) {
                    $ids[] = (int) $evento['pokemon_id'];
                }
            }
        }

        foreach ($terminadas as $exp) {
            $resultado = $exp->eventos['resultado'] ?? [];
            foreach ($resultado['avistados'] ?? [] as $avistado) {
                $ids[] = (int) $avistado['pokemon_id'];
            }
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
        foreach ($exp->eventos['bitacora'] ?? [] as $evento) {
            $bitacora[] = $this->transformarEvento($evento, $nombres);
        }

        $equipo = $exp->team;
        $habitat = $exp->habitat;

        return [
            'id' => $exp->id,
            'equipo' => $equipo !== null ? $equipo->name : 'Sin equipo',
            'habitat' => $habitat !== null ? $habitat->name : 'Sin hábitat',
            'habitat_id' => $exp->habitat_id,
            'nivel' => $exp->nivel,
            'indefinido' => $exp->indefinido,
            'duracion_horas' => $exp->duracion_horas,
            'inicio' => $inicio->toIso8601String(),
            'inicio_vuelta' => $inicioVuelta?->toIso8601String(),
            'fin' => $fin?->toIso8601String(),
            'estado' => $estado,
            'progreso' => $progreso,
            'bitacora' => $bitacora,
        ];
    }

    /**
     * @param  array<array-key, string>  $nombres
     * @return array<string, mixed>
     */
    private function toTerminada(ExploracionActiva $exp, array $nombres): array
    {
        $resultado = $exp->eventos['resultado'] ?? [];

        $avistados = [];
        foreach ($resultado['avistados'] ?? [] as $avistado) {
            $id = (int) $avistado['pokemon_id'];
            $avistados[] = [
                'pokemon_id' => $id,
                'nombre' => $avistado['nombre'] ?? $nombres[$id] ?? null,
            ];
        }

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
                'cantidad' => (int) ($caramelo['cantidad'] ?? 0),
            ];
        }

        $caramelosEv = [];
        foreach ($resultado['caramelos_ev'] ?? [] as $caramelo) {
            $stat = (int) ($caramelo['stat'] ?? 0);
            $caramelosEv[] = [
                'stat' => $stat,
                'stat_nombre' => self::STAT_NOMBRES[$stat] ?? null,
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

        $equipo = $exp->team;
        $habitat = $exp->habitat;

        return [
            'id' => $exp->id,
            'equipo' => $equipo !== null ? $equipo->name : 'Sin equipo',
            'habitat' => $habitat !== null ? $habitat->name : 'Sin hábitat',
            'nivel' => $exp->nivel,
            'resultado' => [
                'avistados' => $avistados,
                'capturados' => $capturados,
                'caramelos_familia' => $caramelosFamilia,
                'caramelos_ev' => $caramelosEv,
                'caramelos_tipo' => $caramelosTipo,
                'exp' => (int) ($resultado['exp'] ?? 0),
            ],
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
            $evento['stat_nombre'] = self::STAT_NOMBRES[(int) ($evento['stat'] ?? 0)] ?? null;
        } elseif (isset($evento['pokemon_id'])) {
            $evento['nombre'] = $nombres[(int) $evento['pokemon_id']] ?? null;
        }

        return $evento;
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
