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
    public function transformarEvento(array $evento, array $nombres): array
    {
        $tipo = $evento['tipo'] ?? 'desconocido';

        if ($tipo === 'caramelo_ev') {
            $statInfo = self::stats()[(int) ($evento['stat'] ?? 0)] ?? null;
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
