<?php

declare(strict_types=1);

namespace Src\Exploraciones\Presentation;

use App\Enums\StatEnum;
use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\Pokemon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Src\Exploraciones\Domain\CalculadorFinExploracion;
use Src\Exploraciones\Domain\CalculadorVueltaExploracion;

/**
 * Métodos de transformación y presentación de exploraciones para la vista index.
 * Centraliza la lógica de mapeo de modelos a arrays que la blade consume.
 */
final class PresentadorExploraciones
{
    /**
     * Datos de stat por id (índice = stat id): nombre en español alineado con
     * el fallback JS de la vista (statName) — StatEnum::label() devuelve
     * 'PS (HP)' para HP y divergiría — y slug usado por el frontend para los
     * iconos de los caramelos EV.
     *
     * @return array<int, array{nombre: string, slug: string}>
     */
    public static function stats(): array
    {
        $nombres = [
            1 => 'PS',
            2 => 'Ataque',
            3 => 'Defensa',
            4 => 'Ataque Especial',
            5 => 'Defensa Especial',
            6 => 'Velocidad',
        ];

        $stats = [];
        foreach ($nombres as $id => $nombre) {
            $stats[$id] = [
                'nombre' => $nombre,
                'slug' => StatEnum::from($id)->slug(),
            ];
        }

        return $stats;
    }

    /**
     * @param  Collection<int, ExploracionActiva>  $activas
     * @param  Collection<int, ExploracionActiva>  $terminadas
     * @return array<array-key, string>
     */
    public function nombresPokemon(Collection $activas, Collection $terminadas): array
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
    public function eventosDe(ExploracionActiva $exp): BaseCollection
    {
        return $exp->eventos ?? collect();
    }

    /**
     * @param  array<string, mixed>  $evento
     * @return list<int>
     */
    public function idsDeEvento(array $evento): array
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
     * Contrato completo de la página de exploraciones.
     *
     * @param  Collection<int, ExploracionActiva>  $activas
     * @param  Collection<int, ExploracionActiva>  $terminadas
     * @return array{activas: list<array<string, mixed>>, terminadas: list<array<string, mixed>>, nivelJugador: int|null}
     */
    public function toView(Collection $activas, Collection $terminadas, ?int $nivelJugador = null): array
    {
        $nombres = $this->nombresPokemon($activas, $terminadas);

        $activasVista = $activas
            ->map(fn (ExploracionActiva $exp): array => $this->toActiva($exp, $nombres))
            ->values()
            ->all();

        $terminadasVista = $terminadas
            ->map(fn (ExploracionActiva $exp): array => $this->toTerminada($exp, $nombres))
            ->values()
            ->all();

        $nivel = $nivelJugador ?? 1;
        foreach ($activasVista as &$activa) {
            $activa['requiere_nivel'] = $activa['min_lvl'] !== null && $nivel < $activa['min_lvl'];
        }
        unset($activa);
        foreach ($terminadasVista as &$terminada) {
            $terminada['requiere_nivel'] = $terminada['min_lvl'] !== null && $nivel < $terminada['min_lvl'];
        }
        unset($terminada);

        return [
            'activas' => $activasVista,
            'terminadas' => $terminadasVista,
            'nivelJugador' => $nivelJugador,
        ];
    }

    /**
     * @param  array<array-key, string>  $nombres
     * @return array<string, mixed>
     */
    public function toActiva(ExploracionActiva $exp, array $nombres): array
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
            '_vista' => true,
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
            'volviendo' => $estado === 'volviendo',
            'estado_label' => $estado === 'volviendo' ? 'Volviendo' : 'Explorando',
            'estado_clase' => $estado === 'volviendo'
                ? 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400'
                : 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
            'progreso' => $progreso,
            'tiempo_perdido' => (int) $this->eventosDe($exp)->get('tiempo_perdido', 0),
            'bitacora' => $bitacora,
        ];
    }

    /**
     * @param  array<array-key, string>  $nombres
     * @return array<string, mixed>
     */
    public function toTerminada(ExploracionActiva $exp, array $nombres): array
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
            $statInfo = self::stats()[$stat] ?? null;
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

        return ResultadoExploracionVista::toView([
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
        ]);
    }

    /**
     * Normaliza una tarjeta activa ya construida (payload legacy o tests de
     * vista) sin depender del modelo Eloquent.
     *
     * @param  array<string, mixed>  $exp
     * @return array<string, mixed>
     */
    public function activaToView(array $exp, ?int $nivelJugador = null): array
    {
        if (($exp['_vista'] ?? false) === true) {
            return $exp;
        }

        $estado = (string) ($exp['estado'] ?? 'explorando');
        $progreso = max(0, min(100, (int) ($exp['progreso'] ?? 0)));
        $bitacora = array_map(
            static fn (array $evento): array => EventoExploracionVista::toView($evento),
            is_array($exp['bitacora'] ?? null) ? $exp['bitacora'] : [],
        );
        $minLvl = $exp['min_lvl'] ?? null;

        return array_merge($exp, [
            '_vista' => true,
            'estado' => $estado,
            'volviendo' => $estado === 'volviendo',
            'estado_label' => $estado === 'volviendo' ? 'Volviendo' : 'Explorando',
            'estado_clase' => $estado === 'volviendo'
                ? 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400'
                : 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
            'progreso' => $progreso,
            'bitacora' => $bitacora,
            'requiere_nivel' => $minLvl !== null && ($nivelJugador ?? 1) < $minLvl,
        ]);
    }

    /**
     * Transforma una tarjeta ya construida (útil para pruebas de vista o
     * payloads legacy) sin depender del modelo Eloquent.
     *
     * @param  array<string, mixed>  $terminada
     * @return array<string, mixed>
     */
    public function terminadaToView(array $terminada): array
    {
        if (($terminada['_vista'] ?? false) === true) {
            return $terminada;
        }

        return ResultadoExploracionVista::toView($terminada);
    }

    /**
     * @param  array<string, mixed>  $evento
     * @param  array<array-key, string>  $nombres
     * @return array<string, mixed>
     */
    public function transformarEvento(array $evento, array $nombres): array
    {
        return EventoExploracionVista::toView($evento, $nombres, completarStats: true);
    }

    /**
     * Nivel mínimo de jugador requerido por el hábitat para el nivel de
     * exploración dado (null = sin restricción). Lo consume el badge
     * "Requiere Nv X" de la vista de exploraciones.
     */
    public function minLvlDelHabitat(?Habitat $habitat, int $nivel): ?int
    {
        return $habitat?->minLvlParaNivel($nivel);
    }

    public function finExploracion(ExploracionActiva $exp, CarbonInterface $inicio): ?CarbonInterface
    {
        return CalculadorFinExploracion::calcular($exp->hora_limite, $exp->duracion_horas, $inicio);
    }

    public function inicioVuelta(CarbonInterface $inicio, ?CarbonInterface $fin): ?CarbonInterface
    {
        return CalculadorVueltaExploracion::inicioVuelta($inicio, $fin);
    }
}
