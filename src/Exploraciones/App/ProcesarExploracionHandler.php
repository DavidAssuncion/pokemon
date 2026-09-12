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
use Src\Exploraciones\Domain\CalculadorFinExploracion;
use Src\Exploraciones\Domain\CalculadorVueltaExploracion;
use Src\Exploraciones\Domain\CapacidadesStats;
use Src\Exploraciones\Domain\EvaluadorExploracion;
use Src\Exploraciones\Domain\SimuladorEncuentros;
use Src\Exploraciones\Domain\ValueObjects\EstadoExplorador;
use Src\Exploraciones\Domain\ValueObjects\PoolHabitat;
use Src\Exploraciones\Domain\ValueObjects\ResultadoBatallaExploracion;
use Src\Shared\Bus\Command;
use Src\Shared\Bus\CommandBus;
use Src\Shared\Bus\CommandHandler;
use Src\Shared\Domain\EscaladorNivelRival;
use Src\Shared\Domain\NivelHelper;
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
 * La capa App/ usa Eloquent (App\Models) e Illuminate directamente por
 * convención del proyecto (la regla DDD solo exige Domain puro).
 */
final class ProcesarExploracionHandler implements CommandHandler
{
    private const MINUTOS_POR_ENCUENTRO = 15;

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

        if ($exploracion->reclutado === null || $exploracion->user === null) {
            return null;
        }

        $inicio = $this->inicioExploracion($exploracion);
        $fin = CalculadorFinExploracion::calcular($exploracion->hora_limite, $exploracion->duracion_horas, $inicio);
        $inicioVuelta = CalculadorVueltaExploracion::inicioVuelta($inicio, $fin);

        /** @var Collection<string, mixed> $eventos */
        $eventos = $exploracion->eventos ?? collect();
        $desde = $this->ultimoProcesado($eventos) ?? $inicio;
        $hasta = $this->limiteTick(now(), $fin, $inicioVuelta);

        $capacidades = FabricaCapacidadesStats::desdeReclutado($exploracion->reclutado, $exploracion->user);
        $dificultad = $exploracion->habitat?->minLvlParaNivel($exploracion->nivel)
            ?? EvaluadorExploracion::dificultad('normal', (int) ($exploracion->habitat?->peligro ?? 1));

        [$terminada, $motivo] = $this->procesarTick($exploracion, $eventos, $desde, $hasta, $capacidades, $dificultad);

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
        CapacidadesStats $capacidades,
        int $dificultad,
    ): array {
        if (! $hasta->greaterThan($desde)) {
            $eventos->put('ultimo_procesado', $hasta->toIso8601String());
            $exploracion->eventos = $eventos;
            $exploracion->save();

            return [false, ''];
        }

        $pool = $this->poolDeHabitat($exploracion);

        $nuevos = SimuladorEncuentros::generarEventosDesdePool(
            $pool,
            $this->numEncuentros((int) abs($hasta->diffInMinutes($desde)), $capacidades, $dificultad),
            $desde,
            $hasta,
            $this->aleatorio,
            $capacidades->permitirEmboscadas($dificultad),
            $capacidades->permitirExcepcionales($dificultad),
        )->aArrays();

        if ($nuevos === []) {
            $eventos->put('ultimo_procesado', $hasta->toIso8601String());
            $exploracion->eventos = $eventos;
            $exploracion->save();

            return [false, ''];
        }

        // Estado del explorador persistido entre ticks (hp/barreras).
        $estadoExplorador = $this->estadoExplorador($eventos);

        $multiplicadorRecuperacion = $capacidades->multiplicadorRecuperacion($dificultad);

        // Al inicio de tick: descanso si HP < 50 %.
        $perdidoTick = $this->aplicarDescansoSiNecesario($exploracion, $eventos, $estadoExplorador, $multiplicadorRecuperacion);

        $resueltos = [];
        $derrota = false;
        $retirada = false;

        // Bucle con índice y cota: las emboscadas evitadas añaden un evento
        // extra que también debe procesarse (y ese extra nunca es emboscada,
        // por lo que no puede volver a añadir ninguno).
        $limiteIteraciones = 2 * count($nuevos);
        $indice = 0;

        while ($indice < count($nuevos) && $indice < $limiteIteraciones) {
            $resuelto = $this->resolverEvento(
                $nuevos[$indice],
                $exploracion,
                $estadoExplorador,
                $eventos,
                $capacidades,
                $dificultad,
            );
            $resueltos[] = $resuelto;
            $perdidoTick += $resuelto['duration_loss'] ?? 0;

            if (($resuelto['resolucion'] ?? '') === 'derrota') {
                $derrota = true;
                break; // Emboscada/encuentro perdido → la exploración termina.
            }

            if (($resuelto['retirada_probable'] ?? false) === true && $this->tirarAleatorio() < 0.5) {
                $retirada = true;
                break;
            }

            // Emboscada evitada: exactamente UN evento extra (hallazgo/neutral).
            if (($resuelto['evitada'] ?? false) === true) {
                $nuevos[] = $this->eventoExtra($pool, $desde, $hasta);
            }

            // Tras cada evento: descanso si el explorador quedó con HP < 50 %.
            $perdidoTick += $this->aplicarDescansoSiNecesario($exploracion, $eventos, $estadoExplorador, $multiplicadorRecuperacion);

            $indice++;
        }

        $bitacora = $eventos->get('bitacora', []);
        $eventos->put('bitacora', [...$bitacora, ...$resueltos]);
        $eventos->put('tiempo_perdido', (int) $eventos->get('tiempo_perdido', 0) + $perdidoTick);
        $eventos->put('explorador', $estadoExplorador->aArray());

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
     * @return array<string, mixed>
     */
    private function resolverEvento(
        array $evento,
        ExploracionActiva $exploracion,
        EstadoExplorador &$estadoExplorador,
        Collection $eventos,
        CapacidadesStats $capacidades,
        int $dificultad,
    ): array {
        $tipo = $evento['tipo'] ?? '';

        if ($tipo === 'emboscada') {
            return $this->resolverEmboscada($evento, $exploracion, $estadoExplorador, $eventos, $capacidades, $dificultad);
        }

        if ($tipo === 'encuentro') {
            return $this->resolverEncuentro($evento, $exploracion, $estadoExplorador, $eventos, $capacidades, $dificultad);
        }

        if ($tipo === 'contratiempo') {
            // RFC: rol individual del reclutado (mitiga según el subtipo).
            $roles = $exploracion->reclutado !== null
                ? [$exploracion->reclutado->rol()]
                : [];

            $resolucion = EvaluadorExploracion::resolverContratiempo(
                subtipo: (string) ($evento['subtype'] ?? 'terreno'),
                roles: $roles,
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
     * @return array<string, mixed>
     */
    private function resolverEncuentro(
        array $evento,
        ExploracionActiva $exploracion,
        EstadoExplorador &$estadoExplorador,
        Collection $eventos,
        CapacidadesStats $capacidades,
        int $dificultad,
    ): array {
        $pokemonId = (int) ($evento['pokemon_id'] ?? 0);
        $salvaje = Pokemon::find($pokemonId);

        if ($salvaje === null) {
            return array_merge($evento, ['resolucion' => 'derrota', 'duration_loss' => 0, 'derrota' => true]);
        }

        // Encuentro con HP < 50 % → descanso a 100 % antes de combatir.
        $perdidoAntes = $this->aplicarDescansoSiNecesario(
            $exploracion,
            $eventos,
            $estadoExplorador,
            $capacidades->multiplicadorRecuperacion($dificultad),
        );

        $resultado = $this->combatirEvento($exploracion, $salvaje, $estadoExplorador, false, $capacidades, $dificultad);

        return array_merge($evento, $this->resolucionCombate($resultado, 'victoria', $estadoExplorador) + ['duration_loss' => $perdidoAntes]);
    }

    /**
     * Emboscada: los pokemon_ids se combaten de uno en uno SIN regenerar
     * barreras entre sub-combates. Si el explorador pierde uno → 'derrota'
     * (no combate el resto). Victoria total → 'superada' (contrato existente:
     * las emboscadas solo reportan avistados, no derrotados).
     *
     * La detección puede evitar la emboscada sin combatir: MAESTRO → siempre
     * 'evitada'; COMPETENTE/EXPERTO → 50 % 'evitada'. La emboscada evitada
     * genera UN evento extra (hallazgo/neutral) en procesarTick.
     *
     * @param  array<string, mixed>  $evento
     * @param  Collection<string, mixed>  $eventos
     * @return array<string, mixed>
     */
    private function resolverEmboscada(
        array $evento,
        ExploracionActiva $exploracion,
        EstadoExplorador &$estadoExplorador,
        Collection $eventos,
        CapacidadesStats $capacidades,
        int $dificultad,
    ): array {
        $ids = array_values(array_map('intval', (array) ($evento['pokemon_ids'] ?? [])));

        if ($ids === []) {
            return array_merge($evento, ['resolucion' => 'derrota', 'duration_loss' => 0, 'derrota' => true]);
        }

        if ($capacidades->deteccionAutoEvasion($dificultad)) {
            return $this->emboscadaEvitada($evento);
        }

        if ($capacidades->permitirEmboscadas($dificultad) && $this->tirarAleatorio() < 0.5) {
            return $this->emboscadaEvitada($evento);
        }

        $subCombates = [];
        $perdidoTotal = 0;

        foreach ($ids as $pokemonId) {
            $salvaje = Pokemon::find($pokemonId);
            if ($salvaje === null) {
                continue;
            }

            // En emboscada con HP < 50 % → primero descanso a 100 %.
            $perdidoTotal += $this->aplicarDescansoSiNecesario(
                $exploracion,
                $eventos,
                $estadoExplorador,
                $capacidades->multiplicadorRecuperacion($dificultad),
            );

            $resultado = $this->combatirEvento($exploracion, $salvaje, $estadoExplorador, true, $capacidades, $dificultad);
            $subCombates[] = [
                'pokemon_id' => $pokemonId,
                'victoria' => $resultado->victoria,
            ];

            if (! $resultado->victoria) {
                return array_merge($evento, $this->resolucionCombate($resultado, 'derrota', $estadoExplorador) + [
                    'sub_combates' => $subCombates,
                    'duration_loss' => $perdidoTotal,
                    'derrota' => true,
                ]);
            }
        }

        // Ningún id del evento resolvió un salvaje existente (caso artificial):
        // la emboscada termina en derrota sin sub-combates.
        if (! isset($resultado)) {
            return array_merge($evento, [
                'resolucion' => 'derrota',
                'duration_loss' => $perdidoTotal,
                'derrota' => true,
            ]);
        }

        return array_merge($evento, $this->resolucionCombate($resultado, 'superada', $estadoExplorador) + [
            'sub_combates' => $subCombates,
            'duration_loss' => $perdidoTotal,
        ]);
    }

    /**
     * Resolución 'evitada' de una emboscada: sin coste ni combate.
     *
     * @param  array<string, mixed>  $evento
     * @return array<string, mixed>
     */
    private function emboscadaEvitada(array $evento): array
    {
        return array_merge($evento, [
            'resolucion' => 'evitada',
            'duration_loss' => 0,
            'evitada' => true,
        ]);
    }

    /**
     * Número de encuentros del tick: el intervalo base (3 min) se reduce con
     * la movilidad y se suma un bonus por rango de exploración.
     */
    private function numEncuentros(int $minutos, CapacidadesStats $capacidades, int $dificultad): int
    {
        if ($minutos <= 0) {
            return 0;
        }

        $intervaloEfectivo = max(1, (int) floor(self::MINUTOS_POR_ENCUENTRO * (1 - $capacidades->reduccionIntervaloMovilidad($dificultad))));

        return intdiv($minutos, $intervaloEfectivo) + $capacidades->bonusEventosExploracion($dificultad);
    }

    /**
     * Genera EXACTAMENTE un evento extra (hallazgo/neutral) para compensar una
     * emboscada evitada. Se genera sin emboscadas permitidas: el extra nunca
     * puede ser una emboscada y, por tanto, no añade más eventos.
     *
     * @return array<string, mixed>
     */
    private function eventoExtra(PoolHabitat $pool, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $extras = SimuladorEncuentros::generarEventosDesdePool($pool, 1, $desde, $hasta, $this->aleatorio, false)->aArrays();

        return $extras[0] ?? ['tipo' => 'neutral', 'detalle' => 'evento neutral'];
    }

    /**
     * Proveedor aleatorio seguro: usa el seam inyectado o mt_rand si no hay.
     */
    private function tirarAleatorio(): float
    {
        if ($this->aleatorio !== null) {
            return ($this->aleatorio)();
        }

        return mt_rand(0, 999) / 1000;
    }

    /**
     * Ejecuta el combate y actualiza el estado del explorador. Tras victoria
     * NO-emboscada regenera las barreras al 100 %; en emboscada secuencial no
     * regenera entre sub-combates. El HP nunca se cura por combate.
     */
    private function combatirEvento(
        ExploracionActiva $exploracion,
        Pokemon $salvaje,
        EstadoExplorador &$estadoExplorador,
        bool $emboscadaSecuencial,
        CapacidadesStats $capacidades,
        int $dificultad,
    ): ResultadoBatallaExploracion {
        $reclutado = $exploracion->reclutado;
        $nivelRival = $this->nivelRival($exploracion);

        $resultado = $this->combate->combatir(
            reclutado: $reclutado,
            salvaje: $salvaje,
            nivelRival: $nivelRival,
            estadoInicial: $this->estadoInicialCombate($estadoExplorador),
            modificadorDanio: $capacidades->bonusDanoCombate($dificultad),
        );

        // Actualizar estado persistido (HP final, máximos y barreras; en
        // victoria NO-emboscada las barreras se regeneran al 100 %).
        $estadoExplorador = $estadoExplorador->actualizarTrasCombate($resultado, $emboscadaSecuencial);

        return $resultado;
    }

    /**
     * Traduce el resultado del combate al contrato de resolución de eventos.
     * Documenta el estado del explorador DESPUÉS del combate (ya con barreras
     * regeneradas al 100 % en victoria no-emboscada).
     *
     * Frontera — el contrato de resolución es el shape persistido en
     * exploracion->eventos y leído por la vista (_evento.blade.php).
     *
*     @return array<string, mixed>
     */
    private function resolucionCombate(ResultadoBatallaExploracion $resultado, string $resolucionVictoria, EstadoExplorador $estadoExplorador): array
    {
        return [
            'resolucion' => $resultado->victoria ? $resolucionVictoria : 'derrota',
            'victoria' => $resultado->victoria,
            'hp_final' => $estadoExplorador->hp,
            'barrera_fisica_final' => $estadoExplorador->barreraFisica,
            'barrera_especial_final' => $estadoExplorador->barreraEspecial,
            'barrera_fisica_max' => $estadoExplorador->barreraFisicaMax,
            'barrera_especial_max' => $estadoExplorador->barreraEspecialMax,
            'log' => $resultado->log->entries(),
            'duration_loss' => 0,
        ];
    }

    /**
     * Nivel del rival escalado: EscaladorNivelRival::escalar(min_lvl del
     * hábitat para el nivel de exploración, nivel del Pokémon explorador). Si
     * el hábitat no tiene mínimo → nivel del Pokémon.
     */
    private function nivelRival(ExploracionActiva $exploracion): int
    {
        $nivelPokemon = NivelHelper::nivelDesdeExperiencia(
            $exploracion->reclutado?->exp->total() ?? 0
        );
        $minLvl = $exploracion->habitat?->minLvlParaNivel($exploracion->nivel);

        if ($minLvl === null) {
            return $nivelPokemon;
        }

        return $this->escalador->escalar($minLvl, $nivelPokemon);
    }

    /**
     * Estado inicial para el combate desde el estado persistido (o null si el
     * explorador no ha combatido aún → comienza al 100 %).
     *
     * @return array{hp: float, barrera_fisica: float, barrera_especial: float}|null
     */
    private function estadoInicialCombate(EstadoExplorador $estadoExplorador): ?array
    {
        if ($estadoExplorador->sinCombate()) {
            return null;
        }

        return [
            'hp' => $estadoExplorador->hp,
            'barrera_fisica' => $estadoExplorador->barreraFisica,
            'barrera_especial' => $estadoExplorador->barreraEspecial,
        ];
    }

    /**
     * Lee el estado persistido del explorador (eventos['explorador']) o
     * devuelve un estado vacío (primer tick → combate al 100 %).
     *
     * @param  Collection<string, mixed>  $eventos
     */
    private function estadoExplorador(Collection $eventos): EstadoExplorador
    {
        /** @var array<string, mixed>|null $estado */
        $estado = $eventos->get('explorador');

        return is_array($estado) ? EstadoExplorador::desdeArray($estado) : EstadoExplorador::vacio();
    }

    /**
     * Aplica descanso hasta el 100 % del HP si el explorador está por debajo
     * del 50 %: recupera 3 % por minuto real (modulado por el multiplicador de
     * recuperación según supervivencia), acumula el tiempo en tiempo_perdido y
     * registra un evento de bitácora. Devuelve los minutos de descanso
     * aplicados (0 si no procede).
     *
     * @param  Collection<string, mixed>  $eventos
     */
    private function aplicarDescansoSiNecesario(
        ExploracionActiva $exploracion,
        Collection $eventos,
        EstadoExplorador &$estadoExplorador,
        float $multiplicadorRecuperacion,
    ): int {
        if (! $estadoExplorador->requiereDescanso()) {
            return 0;
        }

        $pctFaltante = 100 - $estadoExplorador->pctHp();
        $duracionMinutos = (int) ceil($pctFaltante / (self::HP_POR_MINUTO_DESCANSO * $multiplicadorRecuperacion));
        $hpRecuperado = $estadoExplorador->hpMax - $estadoExplorador->hp;

        $estadoExplorador = $estadoExplorador->conHp($estadoExplorador->hpMax);

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
     * Frontera — shape previo del pool (BC con tests que inspeccionan
     * poolHabitat por ReflectionMethod). El pipeline usa poolDeHabitat().
     *
     * @return list<array{id: int, capture_rate: int, hatch: int|null, tipos: list<TipoPokemon>, stats: list<array{stat: int, effort: int}>}>
     *
     * @deprecated Frontera (BC con tests que pasan arrays).
     *
     * @phpstan-ignore method.unused (invocado por tests vía ReflectionMethod)
     */
    private function poolHabitat(ExploracionActiva $exploracion): array
    {
        return $this->poolDeHabitat($exploracion)->aArrays();
    }

    /**
     * Pool de encuentros como colección tipada: pokémon del hábitat asignados
     * al nivel de la exploración, con sus tipos y stats con effort>0.
     */
    private function poolDeHabitat(ExploracionActiva $exploracion): PoolHabitat
    {
        $habitat = $exploracion->habitat;
        if ($habitat === null) {
            return new PoolHabitat();
        }

        return PoolHabitat::desdeArray(
            $habitat->pokemon()
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
                ->all(),
        );
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
