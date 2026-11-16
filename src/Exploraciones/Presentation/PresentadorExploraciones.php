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
use Src\Exploraciones\App\ConsultorEstadoExpedicion;
use Src\Exploraciones\App\FormateadorTiempoRestante;
use Src\Exploraciones\Domain\CalculadorFinExploracion;
use Src\Exploraciones\Domain\CalculadorVueltaExploracion;
use Src\Exploraciones\Domain\EstadoExpedicion;
use Src\Shared\Domain\Items\CatalogoItems;

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

    private ?ConsultorEstadoExpedicion $consultor;

    private ?FormateadorTiempoRestante $formateador;

    /**
     * Las dependencias son opcionales para no romper `new PresentadorExploraciones()`
     * (llamadores legacy y pruebas) y permitir el autowiring del contenedor.
     */
    public function __construct(
        ?ConsultorEstadoExpedicion $consultor = null,
        ?FormateadorTiempoRestante $formateador = null,
    ) {
        $this->consultor = $consultor;
        $this->formateador = $formateador;
    }

    private function consultor(): ConsultorEstadoExpedicion
    {
        return $this->consultor ??= new ConsultorEstadoExpedicion();
    }

    private function formateador(): FormateadorTiempoRestante
    {
        return $this->formateador ??= new FormateadorTiempoRestante();
    }

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
     * `$archivadas` es aditivo: los llamadores existentes siguen invocando el
     * método con tres argumentos y reciben una lista vacía.
     *
     * @param  Collection<int, ExploracionActiva>  $activas
     * @param  Collection<int, ExploracionActiva>  $terminadas
     * @param  Collection<int, ExploracionActiva>|null  $archivadas
     * @return array{activas: list<array<string, mixed>>, terminadas: list<array<string, mixed>>, archivadas: list<array<string, mixed>>, nivelJugador: int|null}
     */
    public function toView(
        Collection $activas,
        Collection $terminadas,
        ?int $nivelJugador = null,
        ?Collection $archivadas = null,
    ): array {
        $archivadas ??= new Collection();

        $nombres = $this->nombresPokemon($activas, $terminadas->concat($archivadas));

        $activasVista = $activas
            ->map(fn (ExploracionActiva $exp): array => $this->toActiva($exp, $nombres))
            ->values()
            ->all();

        $terminadasVista = $terminadas
            ->map(fn (ExploracionActiva $exp): array => $this->toTerminada($exp, $nombres))
            ->values()
            ->all();

        $archivadasVista = $archivadas
            ->map(fn (ExploracionActiva $exp): array => $this->toTerminada($exp, $nombres))
            ->values()
            ->all();

        $nivel = $nivelJugador ?? 1;

        return [
            'activas' => $this->conRequiereNivel($activasVista, $nivel),
            'terminadas' => $this->conRequiereNivel($terminadasVista, $nivel),
            'archivadas' => $this->conRequiereNivel($archivadasVista, $nivel),
            'nivelJugador' => $nivelJugador,
        ];
    }

    /**
     * Añade el flag `requiere_nivel` a cada tarjeta ya construida.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function conRequiereNivel(array $items, int $nivel): array
    {
        return array_map(static function (array $item) use ($nivel): array {
            $minLvl = $item['min_lvl'] ?? null;
            $item['requiere_nivel'] = $minLvl !== null && $nivel < (int) $minLvl;

            return $item;
        }, $items);
    }

    /**
     * Tarjeta de una expedición en curso.
     *
     * @param  array<array-key, string>  $nombres
     * @return array<string, mixed>
     */
    public function toActiva(ExploracionActiva $exp, array $nombres): array
    {
        $ahora = now();
        $estado = $this->consultor()->estado($exp, $ahora);
        [$restanteMin, $restanteLabel] = $this->restanteResumen($exp, $estado, $ahora);

        $reclutado = $exp->reclutado;
        $habitat = $exp->habitat;

        $eventos = $this->eventosDe($exp)->all();
        $explorador = ExploradorVista::toView($reclutado, $eventos);
        $diario = DiarioExploracion::toView($eventos, $nombres, $explorador['nombre'], completarStats: true);

        return array_merge([
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
            'restante_min' => $restanteMin,
            'restante_label' => $restanteLabel,
            'explorador' => $explorador,
            'diario' => $diario,
            'tiempo_perdido' => (int) $this->eventosDe($exp)->get('tiempo_perdido', 0),
            'bitacora' => $diario['eventos'],
        ], $this->camposEstado($exp, $ahora));
    }

    /**
     * Contrato compacto del panel y de la colección cacheable del cliente.
     *
     * Solo incluye los campos que necesita la fila del panel: nada de diario,
     * resultado ni eventos. Los valores derivados llegan calculados.
     *
     * @return array<string, mixed>
     */
    public function toResumen(ExploracionActiva $exp): array
    {
        $ahora = now();
        $estado = $this->consultor()->estado($exp, $ahora);
        $campos = $this->camposEstado($exp, $ahora);
        [$restanteMin, $restanteLabel] = $this->restanteResumen($exp, $estado, $ahora);

        $reclutado = $exp->reclutado;
        $pokemon = $reclutado?->pokemon;
        $nombrePokemon = $pokemon?->name;
        if ($nombrePokemon === null) {
            $nombrePokemon = 'Desconocido';
        }
        $alias = $reclutado?->nombre;
        $pokemonId = $reclutado?->pokemon_id;
        $habitat = $exp->habitat?->name;
        if ($habitat === null) {
            $habitat = 'Sin hábitat';
        }

        return [
            'id' => $exp->id,
            'reclutado_id' => $exp->reclutado_id,
            'pokemon_id' => $pokemonId ?? 0,
            'explorador' => is_string($alias) && trim($alias) !== '' ? $alias : $nombrePokemon,
            'pokemon' => $nombrePokemon,
            'icono' => '/images/iconos_webp/'.($pokemonId ?? 0).'.webp',
            'habitat' => $habitat,
            'nivel' => $exp->nivel,
            'estado' => $campos['estado'],
            'estado_label' => $campos['estado_label'],
            'estado_clase' => $campos['estado_clase'],
            'progreso' => $campos['progreso'],
            'restante_min' => $restanteMin,
            'restante_label' => $restanteLabel,
            'inicio' => $campos['inicio'],
            'inicio_vuelta' => $campos['inicio_vuelta'],
            'fin' => $campos['fin'],
            'indefinido' => $exp->indefinido,
            'archivada' => $campos['archivada'],
            'puede_cancelar' => $campos['puede_cancelar'],
            'puede_recoger' => $campos['puede_recoger'],
            'puede_cerrar' => $campos['puede_cerrar'],
            'puede_archivar' => $campos['puede_archivar'],
        ];
    }

    /**
     * Minutos y etiqueta del panel. Una expedición terminada, archivada o
     * pendiente de procesar no tiene tiempo restante útil: se etiqueta
     * «Pendiente». Una indefinida se etiqueta «Indefinida».
     *
     * @return array{int|null, string|null}
     */
    private function restanteResumen(ExploracionActiva $exp, EstadoExpedicion $estado, CarbonInterface $ahora): array
    {
        if ($estado === EstadoExpedicion::PENDIENTE_RESOLUCION
            || $estado === EstadoExpedicion::TERMINADA
            || $estado === EstadoExpedicion::ARCHIVADA
        ) {
            return [0, 'Pendiente'];
        }

        $fin = $this->consultor()->fin($exp);
        if ($exp->indefinido || $fin === null) {
            return [null, 'Indefinida'];
        }

        $minutos = $this->formateador()->minutos($fin, $ahora);

        return [$minutos, $minutos === null ? null : $this->formateador()->etiqueta($minutos)];
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
                'es_shiny' => (bool) ($capturado['es_shiny'] ?? false),
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

        $items = [];
        foreach (is_array($resultado['items'] ?? null) ? $resultado['items'] : [] as $item) {
            $items[] = $this->itemVista(is_array($item) ? $item : []);
        }

        $reclutado = $exp->reclutado;
        $habitat = $exp->habitat;

        $perdidas = $this->eventosDe($exp)->get('objetos_perdidos', []);
        $perdidas = is_array($perdidas) ? $perdidas : [];

        $eventos = $this->eventosDe($exp)->all();
        $explorador = ExploradorVista::toView($reclutado, $eventos);
        $diario = DiarioExploracion::toView($eventos, $nombres, $explorador['nombre'], completarStats: true);

        $vista = ResultadoExploracionVista::toView([
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
                'items' => $items,
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
                'bonos' => is_array($resultado['bonos'] ?? null) ? $resultado['bonos'] : [],
            ],
            'objetos_perdidos' => $this->objetosPerdidosVista($perdidas, $nombres),
            'motivo_perdida' => $perdidas === [] ? null : $this->motivoPerdida($this->eventosDe($exp)),
            'derrotados' => $this->eventosDe($exp)->get('derrotados', []),
        ]);

        return array_merge($vista, $this->camposEstado($exp, now()));
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
     * Item de botín resuelto para la vista. El contrato persistido solo trae
     * `clave` y `cantidad`; nombre, icono y categoría salen del catálogo
     * compartido, con fallback seguro para claves desconocidas.
     *
     * @param  array<string, mixed>  $item
     * @return array{clave: string, nombre: string, imagen: string, categoria: string, cantidad: int}
     */
    private function itemVista(array $item): array
    {
        $clave = (string) ($item['clave'] ?? '');
        $definicion = $clave !== '' ? CatalogoItems::aPartirDeItemKey($clave) : null;

        if ($definicion === null) {
            return [
                'clave' => $clave,
                'nombre' => 'Desconocido',
                'imagen' => '/images/misc/unknown.webp',
                'categoria' => 'desconocida',
                'cantidad' => (int) ($item['cantidad'] ?? 0),
            ];
        }

        return [
            'clave' => $clave,
            'nombre' => $definicion->nombre,
            'imagen' => $definicion->icono,
            'categoria' => $definicion->categoria->value,
            'cantidad' => (int) ($item['cantidad'] ?? 0),
        ];
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
                case 'baya':
                case 'bonguri':
                    $definicion = CatalogoItems::aPartirDeItemKey((string) $id);
                    if ($definicion !== null) {
                        $label = $definicion->nombre;
                        $imagen = $definicion->icono;
                    } else {
                        $label = $label ?? 'Desconocido';
                        $imagen = '/images/misc/unknown.webp';
                    }
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
            'baya' => 'Baya',
            'bonguri' => 'Bonguri',
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

        $estado = EstadoExpedicion::tryFrom((string) ($exp['estado'] ?? '')) ?? EstadoExpedicion::EXPLORANDO;
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
            'estado' => $estado->value,
            'volviendo' => $estado === EstadoExpedicion::VOLVIENDO,
            'estado_label' => $this->etiquetaEstado($estado),
            'estado_clase' => $this->claseEstado($estado),
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
     * Bloque compartido por tarjetas y resumen: estado, progreso, fechas (ISO
     * y etiquetadas), archivado y flags de acción. Único punto que decide
     * etiquetas y clases de estado.
     *
     * @return array{
     *     estado: string,
     *     estado_label: string,
     *     estado_clase: string,
     *     volviendo: bool,
     *     fase: string,
     *     progreso: int,
     *     archivada: bool,
     *     puede_cancelar: bool,
     *     puede_recoger: bool,
     *     puede_cerrar: bool,
     *     puede_archivar: bool,
     *     inicio: string,
     *     inicio_vuelta: string|null,
     *     fin: string|null,
     *     inicio_label: string,
     *     inicio_vuelta_label: string,
     *     fin_label: string
     * }
     */
    private function camposEstado(ExploracionActiva $exp, CarbonInterface $ahora): array
    {
        $inicio = $this->consultor()->inicio($exp);
        $fin = $this->consultor()->fin($exp);
        $inicioVuelta = $this->consultor()->inicioVuelta($exp);
        $estado = $this->consultor()->estado($exp, $ahora);
        $cerrada = $estado->esTerminada() || $estado === EstadoExpedicion::ARCHIVADA;

        return [
            'estado' => $estado->value,
            'estado_label' => $this->etiquetaEstado($estado),
            'estado_clase' => $this->claseEstado($estado),
            'volviendo' => $estado === EstadoExpedicion::VOLVIENDO,
            'fase' => $estado === EstadoExpedicion::VOLVIENDO ? 'vuelta' : 'ida',
            'progreso' => $cerrada ? 100 : $this->consultor()->progreso($exp, $ahora),
            'archivada' => $exp->archived_at !== null,
            'puede_cancelar' => $exp->regreso === null,
            'puede_recoger' => $exp->regreso === null && $exp->indefinido,
            'puede_cerrar' => $exp->regreso !== null,
            'puede_archivar' => $estado->esTerminada(),
            'inicio' => $inicio->toIso8601String(),
            'inicio_vuelta' => $inicioVuelta?->toIso8601String(),
            'fin' => $fin?->toIso8601String(),
            'inicio_label' => $this->etiquetaFecha($inicio),
            'inicio_vuelta_label' => $this->etiquetaFecha($inicioVuelta),
            'fin_label' => $this->etiquetaFecha($fin),
        ];
    }

    private function etiquetaEstado(EstadoExpedicion $estado): string
    {
        return match ($estado) {
            EstadoExpedicion::EXPLORANDO => 'Explorando',
            EstadoExpedicion::VOLVIENDO => 'Volviendo',
            EstadoExpedicion::PENDIENTE_RESOLUCION => 'Pendiente de procesar',
            EstadoExpedicion::TERMINADA => 'Terminada',
            EstadoExpedicion::ARCHIVADA => 'Archivada',
            EstadoExpedicion::CANCELADA => 'Cancelada',
        };
    }

    private function claseEstado(EstadoExpedicion $estado): string
    {
        return match ($estado) {
            EstadoExpedicion::EXPLORANDO => 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
            EstadoExpedicion::VOLVIENDO => 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400',
            EstadoExpedicion::PENDIENTE_RESOLUCION => 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400',
            EstadoExpedicion::TERMINADA => 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
            EstadoExpedicion::ARCHIVADA => 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300',
            EstadoExpedicion::CANCELADA => 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
        };
    }

    /**
     * Fecha legible para los parciales sin JavaScript (`d/m/Y H:i`, zona del
     * servidor). Sin fecha devuelve un guion largo.
     */
    public function etiquetaFecha(?CarbonInterface $fecha): string
    {
        return $fecha?->format('d/m/Y H:i') ?? '—';
    }
}
