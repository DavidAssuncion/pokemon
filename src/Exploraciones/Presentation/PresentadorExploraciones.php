<?php

declare(strict_types=1);

namespace Src\Exploraciones\Presentation;

use App\Enums\StatEnum;
use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Support\CadenasEvolutivas;
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
    /** @var array<int, Collection<int, Pokemon>> */
    private array $miembrosPorCadenaCache = [];

    /** @var array<int, true> */
    private array $cadenasConsultadas = [];

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
            foreach ($this->eventosDe($exp)->get('bitacora', []) as $evento) {
                foreach ($this->idsDeEvento($evento) as $id) {
                    $ids[] = $id;
                }
            }

            $resultado = $this->eventosDe($exp)->get('resultado', []);
            foreach ($resultado['capturados'] ?? [] as $capturado) {
                $ids[] = (int) $capturado['pokemon_id'];
            }

            // Una captura perdida puede haber quedado fuera de resultado al
            // restar las pérdidas; su nombre solo se conserva en objetos_perdidos.
            foreach ($this->eventosDe($exp)->get('objetos_perdidos', []) as $perdida) {
                if (($perdida['tipo'] ?? null) === 'captura') {
                    $ids[] = (int) ($perdida['id'] ?? 0);
                }
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
     * @return BaseCollection<string, mixed>
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

        $reclutado = $exp->reclutado;
        $habitat = $exp->habitat;

        $eventos = $this->eventosDe($exp)->all();
        $explorador = ExploradorVista::toView($reclutado, $eventos);
        $diario = DiarioExploracion::toView($eventos, $nombres, $explorador['nombre'], completarStats: true);

        $restanteMin = $fin !== null
            ? max(0, (int) ceil($ahora->diffInMinutes($fin, false)))
            : null;

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
            'fase' => $estado === 'volviendo' ? 'vuelta' : 'ida',
            'restante_min' => $restanteMin,
            'restante_label' => $restanteMin !== null ? $this->restanteLabel($restanteMin) : null,
            'explorador' => $explorador,
            'diario' => $diario,
            'tiempo_perdido' => (int) $this->eventosDe($exp)->get('tiempo_perdido', 0),
            'bitacora' => $diario['eventos'],
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

        $perdidas = $this->eventosDe($exp)->get('objetos_perdidos', []);
        $perdidas = is_array($perdidas) ? $perdidas : [];

        $eventos = $this->eventosDe($exp)->all();
        $explorador = ExploradorVista::toView($reclutado, $eventos);
        $diario = DiarioExploracion::toView($eventos, $nombres, $explorador['nombre'], completarStats: true);

        return ResultadoExploracionVista::toView([
            'id' => $exp->id,
            'explorador' => $explorador,
            'diario' => $diario,
            'exp_reclutado' => (int) ($resultado['exp_reclutado'] ?? 0),
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
                'objetos_perdidos' => $perdidas,
                'incidentes' => $resultado['incidentes'] ?? [
                    'encuentros' => 0,
                    'victorias' => 0,
                    'huidas' => 0,
                    'emboscadas' => 0,
                    'contratiempos' => 0,
                ],
            ],
            'objetos_perdidos' => $this->objetosPerdidosVista($perdidas, $nombres),
            'motivo_perdida' => $perdidas === [] ? null : $this->motivoPerdida($this->eventosDe($exp)),
            'derrotados' => $this->eventosDe($exp)->get('derrotados', []),
        ]);
    }

    /**
     * Motivo legible de la pérdida de objetos. Se deriva del evento raíz
     * `derrota`; sin él se usa el texto genérico de derrota.
     *
     * @param  BaseCollection<string, mixed>  $eventos
     */
    private function motivoPerdida(BaseCollection $eventos): string
    {
        $derrota = $eventos->get('derrota');
        $reason = is_array($derrota) && is_string($derrota['reason'] ?? null)
            ? trim($derrota['reason'])
            : '';
        $reason = $reason === '' ? null : $reason;

        return match ($reason) {
            null => 'La expedición terminó en derrota y se perdió parte del botín.',
            'explorador_debilitado' => 'Tu explorador cayó debilitado en combate y perdió parte del botín.',
            default => 'La expedición terminó en derrota ('.str_replace('_', ' ', $reason).') y se perdió parte del botín.',
        };
    }

    /**
     * Enriquece las pérdidas persistidas (`tipo`, `id`, `label`,
     * `cantidad_perdida`) con el nombre y la imagen que consume la vista.
     * El contrato persistido no cambia: la resolución es solo de presentación.
     *
     * @param  list<array<string, mixed>>  $perdidas
     * @param  array<array-key, string>  $nombres
     * @return list<array{tipo: string, tipo_label: string, id: int|string, label: string|null, imagen: string|null, cantidad_perdida: int}>
     */
    private function objetosPerdidosVista(array $perdidas, array $nombres): array
    {
        $cadenas = [];
        foreach ($perdidas as $perdida) {
            if (($perdida['tipo'] ?? null) === 'familia') {
                $cadenas[] = (int) ($perdida['id'] ?? 0);
            }
        }

        // Una sola consulta por cadena nueva, cacheada entre tarjetas.
        $miembrosPorCadena = $this->miembrosDeCadenas($cadenas);

        $vista = [];
        foreach ($perdidas as $perdida) {
            $tipo = (string) ($perdida['tipo'] ?? '');
            $id = $perdida['id'] ?? '';
            $label = $this->labelPerdidaPersistido($perdida['label'] ?? null);
            $imagen = null;

            switch ($tipo) {
                case 'familia':
                    $miembros = $miembrosPorCadena[(int) $id] ?? null;
                    $base = $miembros?->sortBy('species_id')->first();
                    if ($base !== null) {
                        $label = $base->name;
                        $imagen = "/images/candy_pokemon/{$base->id}.webp";
                    }
                    break;
                case 'ev':
                    $stat = StatEnum::fromId((int) $id);
                    if ($stat !== null) {
                        $label = $stat->label();
                        $imagen = "/images/candy_ev/{$stat->slug()}.webp";
                    }
                    break;
                case 'tipo':
                    $slug = (string) $id;
                    $label = $label ?? ($slug !== '' ? ucfirst($slug) : null);
                    $imagen = $slug !== '' ? "/images/candy_type/{$slug}.webp" : null;
                    break;
                case 'captura':
                    $pokemonId = (int) $id;
                    $label = $nombres[$pokemonId] ?? 'Pokémon #'.$pokemonId;
                    $imagen = $pokemonId > 0 ? "/images/iconos_webp/{$pokemonId}.webp" : null;
                    break;
            }

            $vista[] = [
                'tipo' => $tipo,
                'tipo_label' => $this->tipoLabelPerdida($tipo),
                'id' => $id,
                'label' => $label,
                'imagen' => $imagen,
                'cantidad_perdida' => (int) ($perdida['cantidad_perdida'] ?? 0),
            ];
        }

        return $vista;
    }

    /**
     * Miembros de las cadenas solicitadas, reutilizando las ya consultadas
     * durante la misma página para evitar el N+1 entre tarjetas.
     *
     * @param  list<int>  $cadenas
     * @return array<int, Collection<int, Pokemon>>
     */
    private function miembrosDeCadenas(array $cadenas): array
    {
        $ids = array_values(array_unique(array_filter(
            $cadenas,
            static fn (int $id): bool => $id > 0,
        )));

        if ($ids === []) {
            return [];
        }

        $faltan = array_values(array_filter(
            $ids,
            fn (int $id): bool => ! isset($this->cadenasConsultadas[$id]),
        ));

        if ($faltan !== []) {
            $nuevos = CadenasEvolutivas::miembrosDe($faltan);

            foreach ($faltan as $id) {
                $this->cadenasConsultadas[$id] = true;
            }

            $this->miembrosPorCadenaCache += $nuevos;
        }

        return array_intersect_key($this->miembrosPorCadenaCache, array_flip($ids));
    }

    /**
     * Un label numérico es un dato histórico sin valor como nombre: se
     * descarta para que cada tipo resuelva el suyo.
     */
    private function labelPerdidaPersistido(mixed $label): ?string
    {
        if (! is_scalar($label)) {
            return null;
        }

        $texto = trim((string) $label);

        return $texto === '' || is_numeric($texto) ? null : $texto;
    }

    private function tipoLabelPerdida(string $tipo): string
    {
        return match ($tipo) {
            'familia' => 'Familia',
            'ev' => 'EV',
            'tipo' => 'Tipo',
            'captura' => 'Captura',
            default => ucfirst($tipo),
        };
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
        $bitacora = array_values(array_filter(
            array_map(
                static fn (array $evento): array => EventoExploracionVista::toView($evento),
                is_array($exp['bitacora'] ?? null) ? $exp['bitacora'] : [],
            ),
            static fn (array $evento): bool => ($evento['oculto'] ?? false) !== true,
        ));
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

    /**
     * Etiqueta legible del tiempo restante hasta el fin de la expedición.
     */
    private function restanteLabel(int $minutos): string
    {
        if ($minutos <= 0) {
            return 'Menos de 1 min';
        }

        if ($minutos < 60) {
            return "{$minutos} min";
        }

        $horas = intdiv($minutos, 60);
        $resto = $minutos % 60;

        return $resto === 0 ? "{$horas} h" : "{$horas} h {$resto} min";
    }
}
