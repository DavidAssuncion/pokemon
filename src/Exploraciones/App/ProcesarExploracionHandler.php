<?php

declare(strict_types=1);

namespace Src\Exploraciones\App;

use App\Models\ExploracionActiva;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Collection;
use LogicException;
use Src\Exploraciones\Domain\EvaluadorExploracion;
use Src\Exploraciones\Domain\SimuladorEncuentros;
use Src\Shared\Bus\Command;
use Src\Shared\Bus\CommandBus;
use Src\Shared\Bus\CommandHandler;
use Src\Shared\Domain\EscaladorNivelRival;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Tick de expedición individual (RF-05/D2): genera un evento por slot y lo
 * resuelve con combate real 1v1 (CombateExploracion) en lugar del evaluador
 * de capacidad. El explorador es un ÚNICO reclutado (sin roles ni sinergia).
 *
 * Tras cada combate: victoria → regenera barreras al 100 % (solo si NO es
 * emboscada secuencial); el HP no se cura por combate. Si HP < 50 % → descanso
 * hasta 100 % a 3 %/min real (acumulado en tiempo_perdido + evento bitácora).
 * Si el explorador pierde un combate → la exploración termina (derrota).
 *
 * @todo Excepción temporal a la regla de dependencias: src/ importa
 *       App\Models/Illuminate (deuda WIP). Ticket v2: extraer repositorio.
 */
final class ProcesarExploracionHandler implements CommandHandler
{
    private const MINUTOS_POR_ENCUENTRO = 3;

    /** Umbral de HP (porcentaje del máximo) para forzar descanso. */
    private const UMBRAL_DESCANSO_HP = 50;

    /** Porcentaje de HP recuperado por minuto real de descanso. */
    private const HP_POR_MINUTO_DESCANSO = 3;

    private readonly ?Closure $aleatorio;

    /**
     * @param  callable():float|null  $aleatorio  Proveedor determinista [0,1)
     *                                            para TEST (seam). null → mt_rand.
     */
    public function __construct(
        private readonly CommandBus $bus,
        private readonly CombateExploracion $combate,
        private readonly EscaladorNivelRival $escalador,
        ?callable $aleatorio = null,
    ) {
        $this->aleatorio = $aleatorio !== null ? Closure::fromCallable($aleatorio) : null;
    }

    public function handle(Command $command): mixed
    {
        if (! $command instanceof ProcesarExploracionCommand) {
            throw new LogicException('ProcesarExploracionHandler requires a ProcesarExploracionCommand.');
        }

        $exploracion = $command->exploracion;

        if ($exploracion->regreso !== null) {
            return null;
        }

        $inicio = $this->inicioExploracion($exploracion);
        $fin = $this->finExploracion($exploracion, $inicio);
        $inicioVuelta = $fin !== null
            ? $fin->copy()->subMinutes(intdiv((int) abs($fin->diffInMinutes($inicio)), 4))
            : null;

        /** @var Collection<string, mixed> $eventos */
        $eventos = $exploracion->eventos ?? collect();
        $desde = $this->ultimoProcesado($eventos) ?? $inicio;
        $hasta = $this->limiteTick(now(), $fin, $inicioVuelta);

        [$terminada, $motivo] = $this->procesarTick($exploracion, $eventos, $desde, $hasta);

        $completada = $command->forzarRegreso
            || ($inicioVuelta !== null && now()->greaterThanOrEqualTo($inicioVuelta))
            || $terminada;

        if ($completada) {
            $eventos->put($motivo === 'derrota' ? 'derrota' : 'retirada', [
                'reason' => $motivo === 'derrota' ? 'explorador_debilitado' : 'grupo_enemigo',
                'timestamp' => now()->toIso8601String(),
            ]);
            $exploracion->eventos = $eventos;
            $exploracion->save();

            $this->bus->dispatch(new FinalizarExploracionCommand($exploracion));
        }

        return null;
    }

    /**
     * Genera y resuelve los eventos del tick con combate real. Devuelve
     * [terminada, motivo] donde motivo es 'derrota'|'retirada'.
     *
     * @param  Collection<string, mixed>  $eventos
     * @return array{0: bool, 1: string}
     */
    private function procesarTick(
        ExploracionActiva $exploracion,
        Collection $eventos,
        CarbonInterface $desde,
        CarbonInterface $hasta,
    ): array {
        if (! $hasta->greaterThan($desde)) {
            $eventos->put('ultimo_procesado', $hasta->toIso8601String());
            $exploracion->eventos = $eventos;
            $exploracion->save();

            return [false, ''];
        }

        $nuevos = SimuladorEncuentros::generarEventos(
            $this->poolHabitat($exploracion),
            intdiv((int) abs($hasta->diffInMinutes($desde)), self::MINUTOS_POR_ENCUENTRO),
            $desde,
            $hasta,
            $this->aleatorio,
        );

        if ($nuevos === []) {
            $eventos->put('ultimo_procesado', $hasta->toIso8601String());
            $exploracion->eventos = $eventos;
            $exploracion->save();

            return [false, ''];
        }

        // Estado del explorador persistido entre ticks (hp/barreras).
        $estadoExplorador = $this->estadoExplorador($eventos);

        // Al inicio de tick: descanso si HP < 50 %.
        $perdidoTick = $this->aplicarDescansoSiNecesario($exploracion, $eventos, $estadoExplorador);

        $resueltos = [];
        $derrota = false;
        $retirada = false;

        foreach ($nuevos as $evento) {
            $resuelto = $this->resolverEvento($evento, $exploracion, $estadoExplorador, $eventos);
            $resueltos[] = $resuelto;
            $perdidoTick += $resuelto['duration_loss'] ?? 0;

            if (($resuelto['resolucion'] ?? '') === 'derrota') {
                $derrota = true;
                break; // Emboscada/encuentro perdido → la exploración termina.
            }

            if (($resuelto['retirada_probable'] ?? false) === true && $this->aleatorio() < 0.5) {
                $retirada = true;
                break;
            }

            // Tras cada evento: descanso si el explorador quedó con HP < 50 %.
            $perdidoTick += $this->aplicarDescansoSiNecesario($exploracion, $eventos, $estadoExplorador);
        }

        $bitacora = $eventos->get('bitacora', []);
        $eventos->put('bitacora', [...$bitacora, ...$resueltos]);
        $eventos->put('tiempo_perdido', (int) $eventos->get('tiempo_perdido', 0) + $perdidoTick);
        $eventos->put('explorador', $estadoExplorador);

        if ($retirada) {
            $eventos->put('retirada', [
                'reason' => 'grupo_enemigo',
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        // D2: adelantar ultimo_procesado hasta la próxima ejecución.
        $eventos->put('ultimo_procesado', $hasta->copy()->addMinutes($perdidoTick)->toIso8601String());

        $exploracion->eventos = $eventos;
        $exploracion->save();

        return [$derrota || $retirada, $derrota ? 'derrota' : ($retirada ? 'retirada' : '')];
    }

    /**
     * Resuelve un evento con combate real (encuentro/emboscada) o el evaluador
     * para contratiempos. Mantiene el contrato de resolución existente
     * (resolucion, duration_loss) para FinalizarExploracionHandler.
     *
     * @param  array<string, mixed>  $evento
     * @param  Collection<string, mixed>  $eventos
     * @param  array{hp: float, hp_max: float, barrera_fisica: float, barrera_fisica_max: float, barrera_especial: float, barrera_especial_max: float}  $estadoExplorador
     * @return array<string, mixed>
     */
    private function resolverEvento(array $evento, ExploracionActiva $exploracion, array &$estadoExplorador, Collection $eventos): array
    {
        $tipo = $evento['tipo'] ?? '';

        if ($tipo === 'emboscada') {
            return $this->resolverEmboscada($evento, $exploracion, $estadoExplorador, $eventos);
        }

        if ($tipo === 'encuentro') {
            return $this->resolverEncuentro($evento, $exploracion, $estadoExplorador, $eventos);
        }

        if ($tipo === 'contratiempo') {
            $resolucion = EvaluadorExploracion::resolverContratiempo(
                subtipo: (string) ($evento['subtype'] ?? 'terreno'),
                roles: [], // Exploración individual: sin roles de equipo.
            );

            return array_merge($evento, $resolucion);
        }

        return $evento;
    }

    /**
     * Combate 1v1 real contra un salvaje del evento (pokemon_id).
     * Victoria → 'victoria'; derrota → 'derrota' (la exploración termina).
     *
     * @param  array<string, mixed>  $evento
     * @param  Collection<string, mixed>  $eventos
     * @param  array{hp: float, hp_max: float, barrera_fisica: float, barrera_fisica_max: float, barrera_especial: float, barrera_especial_max: float}  $estadoExplorador
     * @return array<string, mixed>
     */
    private function resolverEncuentro(array $evento, ExploracionActiva $exploracion, array &$estadoExplorador, Collection $eventos): array
    {
        $pokemonId = (int) ($evento['pokemon_id'] ?? 0);
        $salvaje = Pokemon::find($pokemonId);

        if ($salvaje === null) {
            return array_merge($evento, ['resolucion' => 'derrota', 'duration_loss' => 0, 'derrota' => true]);
        }

        // Encuentro con HP < 50 % → descanso a 100 % antes de combatir.
        $perdidoAntes = $this->aplicarDescansoSiNecesario($exploracion, $eventos, $estadoExplorador);

        $resultado = $this->combatirEvento($exploracion, $salvaje, $estadoExplorador, false);

        return array_merge($evento, $this->resolucionCombate($resultado, 'victoria', $estadoExplorador) + ['duration_loss' => $perdidoAntes]);
    }

    /**
     * Emboscada: los pokemon_ids se combaten de uno en uno SIN regenerar
     * barreras entre sub-combates. Si el explorador pierde uno → 'derrota'
     * (no combate el resto). Victoria total → 'superada' (contrato existente:
     * las emboscadas solo reportan avistados, no derrotados).
     *
     * @param  array<string, mixed>  $evento
     * @param  Collection<string, mixed>  $eventos
     * @param  array{hp: float, hp_max: float, barrera_fisica: float, barrera_fisica_max: float, barrera_especial: float, barrera_especial_max: float}  $estadoExplorador
     * @return array<string, mixed>
     */
    private function resolverEmboscada(array $evento, ExploracionActiva $exploracion, array &$estadoExplorador, Collection $eventos): array
    {
        $ids = array_values(array_map('intval', (array) ($evento['pokemon_ids'] ?? [])));

        if ($ids === []) {
            return array_merge($evento, ['resolucion' => 'derrota', 'duration_loss' => 0, 'derrota' => true]);
        }

        $subCombates = [];
        $perdidoTotal = 0;

        foreach ($ids as $pokemonId) {
            $salvaje = Pokemon::find($pokemonId);
            if ($salvaje === null) {
                continue;
            }

            // En emboscada con HP < 50 % → primero descanso a 100 %.
            $perdidoTotal += $this->aplicarDescansoSiNecesario($exploracion, $eventos, $estadoExplorador);

            $resultado = $this->combatirEvento($exploracion, $salvaje, $estadoExplorador, true);
            $subCombates[] = [
                'pokemon_id' => $pokemonId,
                'victoria' => $resultado['victoria'],
            ];

            if (! $resultado['victoria']) {
                return array_merge($evento, $this->resolucionCombate($resultado, 'derrota', $estadoExplorador) + [
                    'sub_combates' => $subCombates,
                    'duration_loss' => $perdidoTotal,
                    'derrota' => true,
                ]);
            }
        }

        return array_merge($evento, $this->resolucionCombate($resultado, 'superada', $estadoExplorador) + [
            'sub_combates' => $subCombates,
            'duration_loss' => $perdidoTotal,
        ]);
    }

    /**
     * Ejecuta el combate y actualiza el estado del explorador. Tras victoria
     * NO-emboscada regenera las barreras al 100 %; en emboscada secuencial no
     * regenera entre sub-combates. El HP nunca se cura por combate.
     *
     * @param  array{hp: float, hp_max: float, barrera_fisica: float, barrera_fisica_max: float, barrera_especial: float, barrera_especial_max: float}  $estadoExplorador
     * @return array<string, mixed>
     */
    private function combatirEvento(
        ExploracionActiva $exploracion,
        Pokemon $salvaje,
        array &$estadoExplorador,
        bool $emboscadaSecuencial,
    ): array {
        $reclutado = $exploracion->reclutado;
        $nivelRival = $this->nivelRival($exploracion);

        $resultado = $this->combate->combatir(
            reclutado: $reclutado,
            salvaje: $salvaje,
            nivelRival: $nivelRival,
            estadoInicial: $this->estadoInicialCombate($estadoExplorador),
        );

        // Actualizar estado persistido.
        $estadoExplorador['hp'] = $resultado['hp_final'];
        $estadoExplorador['hp_max'] = $resultado['hp_max'];
        $estadoExplorador['barrera_fisica'] = $resultado['barrera_fisica_final'];
        $estadoExplorador['barrera_fisica_max'] = $resultado['barrera_fisica_max'];
        $estadoExplorador['barrera_especial'] = $resultado['barrera_especial_final'];
        $estadoExplorador['barrera_especial_max'] = $resultado['barrera_especial_max'];

        // Victoria no-emboscada → regenerar barreras al 100 %.
        if ($resultado['victoria'] && ! $emboscadaSecuencial) {
            $estadoExplorador['barrera_fisica'] = $resultado['barrera_fisica_max'];
            $estadoExplorador['barrera_especial'] = $resultado['barrera_especial_max'];
        }

        $resultado['emboscada_secuencial'] = $emboscadaSecuencial;

        return $resultado;
    }

    /**
     * Traduce el resultado del combate al contrato de resolución de eventos.
     * Documenta el estado del explorador DESPUÉS del combate (ya con barreras
     * regeneradas al 100 % en victoria no-emboscada).
     *
     * @param  array<string, mixed>  $resultado
     * @param  array{hp: float, hp_max: float, barrera_fisica: float, barrera_fisica_max: float, barrera_especial: float, barrera_especial_max: float}  $estadoExplorador
     * @return array<string, mixed>
     */
    private function resolucionCombate(array $resultado, string $resolucionVictoria, array $estadoExplorador): array
    {
        return [
            'resolucion' => $resultado['victoria'] ? $resolucionVictoria : 'derrota',
            'victoria' => $resultado['victoria'],
            'hp_final' => $estadoExplorador['hp'],
            'barrera_fisica_final' => $estadoExplorador['barrera_fisica'],
            'barrera_especial_final' => $estadoExplorador['barrera_especial'],
            'barrera_fisica_max' => $estadoExplorador['barrera_fisica_max'],
            'barrera_especial_max' => $estadoExplorador['barrera_especial_max'],
            'log' => $resultado['log'],
            'duration_loss' => 0,
        ];
    }

    /**
     * Nivel del rival escalado: EscaladorNivelRival::escalar(min_lvl del
     * hábitat para el nivel de exploración, nivel del jugador). Si el hábitat
     * no tiene mínimo → nivel del jugador.
     */
    private function nivelRival(ExploracionActiva $exploracion): int
    {
        $nivelJugador = $exploracion->user?->nivel() ?? 1;
        $minLvl = $exploracion->habitat?->getAttribute('min_lvl_'.$exploracion->nivel);

        if ($minLvl === null) {
            return $nivelJugador;
        }

        return $this->escalador->escalar((int) $minLvl, $nivelJugador);
    }

    /**
     * Estado inicial para el combate desde el estado persistido (o null si el
     * explorador no ha combatido aún → comienza al 100 %).
     *
     * @param  array{hp: float, hp_max: float, barrera_fisica: float, barrera_fisica_max: float, barrera_especial: float, barrera_especial_max: float}|null  $estadoExplorador
     * @return array{hpa: float, barrera_fisica: float, barrera_especial: float}|null
     */
    private function estadoInicialCombate(?array $estadoExplorador): ?array
    {
        if ($estadoExplorador === null || ($estadoExplorador['hp_max'] ?? 0) <= 0) {
            return null;
        }

        return [
            'hp' => $estadoExplorador['hp'],
            'barrera_fisica' => $estadoExplorador['barrera_fisica'],
            'barrera_especial' => $estadoExplorador['barrera_especial'],
        ];
    }

    /**
     * Lee el estado persistido del explorador (eventos['explorador']) o
     * devuelve un estado vacío (primer tick → combate al 100 %).
     *
     * @param  Collection<string, mixed>  $eventos
     * @return array{hp: float, hp_max: float, barrera_fisica: float, barrera_fisica_max: float, barrera_especial: float, barrera_especial_max: float}
     */
    private function estadoExplorador(Collection $eventos): array
    {
        /** @var array<string, mixed>|null $estado */
        $estado = $eventos->get('explorador');

        return [
            'hp' => (float) ($estado['hp'] ?? 0),
            'hp_max' => (float) ($estado['hp_max'] ?? 0),
            'barrera_fisica' => (float) ($estado['barrera_fisica'] ?? 0),
            'barrera_fisica_max' => (float) ($estado['barrera_fisica_max'] ?? 0),
            'barrera_especial' => (float) ($estado['barrera_especial'] ?? 0),
            'barrera_especial_max' => (float) ($estado['barrera_especial_max'] ?? 0),
        ];
    }

    /**
     * Aplica descanso hasta el 100 % del HP si el explorador está por debajo
     * del 50 %: recupera 3 % por minuto real, acumula el tiempo en
     * tiempo_perdido y registra un evento de bitácora. Devuelve los minutos de
     * descanso aplicados (0 si no procede).
     *
     * @param  Collection<string, mixed>  $eventos
     * @param  array{hp: float, hp_max: float, barrera_fisica: float, barrera_fisica_max: float, barrera_especial: float, barrera_especial_max: float}  $estadoExplorador
     */
    private function aplicarDescansoSiNecesario(
        ExploracionActiva $exploracion,
        Collection $eventos,
        array &$estadoExplorador,
    ): int {
        if (($estadoExplorador['hp_max'] ?? 0) <= 0) {
            return 0; // Aún no ha combatido: comienza al 100 %.
        }

        $pctActual = ($estadoExplorador['hp'] / $estadoExplorador['hp_max']) * 100;

        if ($pctActual >= self::UMBRAL_DESCANSO_HP) {
            return 0;
        }

        $pctFaltante = 100 - $pctActual;
        $duracionMinutos = (int) ceil($pctFaltante / self::HP_POR_MINUTO_DESCANSO);
        $hpRecuperado = $estadoExplorador['hp_max'] - $estadoExplorador['hp'];

        $estadoExplorador['hp'] = $estadoExplorador['hp_max'];

        $bitacora = $eventos->get('bitacora', []);
        $eventos->put('bitacora', [...$bitacora, [
            'tipo' => 'descanso',
            'timestamp' => now()->toIso8601String(),
            'duracion_minutos' => $duracionMinutos,
            'hp_recuperado' => $hpRecuperado,
        ]]);

        return $duracionMinutos;
    }

    private function inicioExploracion(ExploracionActiva $exploracion): CarbonInterface
    {
        if ($exploracion->inicio_exploracion !== null) {
            return $exploracion->inicio_exploracion->copy();
        }

        if ($exploracion->created_at !== null) {
            return $exploracion->created_at->copy();
        }

        return now();
    }

    private function finExploracion(ExploracionActiva $exploracion, CarbonInterface $inicio): ?CarbonInterface
    {
        if ($exploracion->hora_limite !== null) {
            return Carbon::today()->setTimeFromTimeString($exploracion->hora_limite);
        }

        if ($exploracion->duracion_horas !== null) {
            return $inicio->copy()->addHours($exploracion->duracion_horas);
        }

        return null;
    }

    private function limiteTick(
        CarbonInterface $ahora,
        ?CarbonInterface $fin,
        ?CarbonInterface $inicioVuelta,
    ): CarbonInterface {
        $limite = $ahora;

        if ($fin !== null && $fin->lessThan($limite)) {
            $limite = $fin;
        }

        if ($inicioVuelta !== null && $inicioVuelta->lessThan($limite)) {
            $limite = $inicioVuelta;
        }

        return $limite;
    }

    /**
     * @param  Collection<string, mixed>  $eventos
     */
    private function ultimoProcesado(Collection $eventos): ?CarbonInterface
    {
        $ultimo = $eventos->get('ultimo_procesado');

        return is_string($ultimo) ? Carbon::parse($ultimo) : null;
    }

    /**
     * Pool de encuentros: pokémon del hábitat asignados al nivel de la
     * exploración, con sus tipos y stats con effort>0 (para caramelos EV).
     *
     * @return array<int, array{id: int, capture_rate: int, hatch: int|null, tipos: list<TipoPokemon>, stats: list<array{stat: int, effort: int}>}>
     */
    private function poolHabitat(ExploracionActiva $exploracion): array
    {
        $habitat = $exploracion->habitat;
        if ($habitat === null) {
            return [];
        }

        return $habitat->pokemon()
            ->wherePivot('level', $exploracion->nivel)
            ->get()
            ->loadMissing('types', 'stats')
            ->map(fn (Pokemon $pokemon) => [
                'id' => $pokemon->id,
                'capture_rate' => $pokemon->capture_rate,
                'hatch' => $pokemon->hatch,
                'tipos' => $this->tiposDe($pokemon),
                'stats' => $pokemon->stats
                    ->filter(fn (PokemonStat $stat) => $stat->effort > 0)
                    ->map(fn (PokemonStat $stat) => [
                        'stat' => $stat->stat->value,
                        'effort' => $stat->effort,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /** @return list<TipoPokemon> */
    private function tiposDe(Pokemon $pokemon): array
    {
        return $pokemon->types
            ->map(fn (PokemonType $tipo): TipoPokemon => TipoPokemon::from($tipo->type->value))
            ->values()
            ->all();
    }
}
