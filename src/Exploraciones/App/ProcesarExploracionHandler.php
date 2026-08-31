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
use Src\Exploraciones\Domain\CalculadorCapacidadEquipo;
use Src\Exploraciones\Domain\EvaluadorExploracion;
use Src\Exploraciones\Domain\RolExploracion;
use Src\Exploraciones\Domain\SimuladorEncuentros;
use Src\Exploraciones\Domain\SinergiaEquipo;
use Src\Shared\Bus\Command;
use Src\Shared\Bus\CommandBus;
use Src\Shared\Bus\CommandHandler;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Tick de expedición (RF-05/D2): genera un evento por slot, lo resuelve con el
 * EvaluadorExploracion, acumula el tiempo perdido y adelanta `ultimo_procesado`
 * hasta la próxima ejecución (incluso en el futuro). Si hay retirada, despacha
 * FinalizarExploracionCommand a través del bus.
 *
 * @todo Excepción temporal a la regla de dependencias: src/ importa
 *       App\Models/Illuminate (deuda WIP). Ticket v2: extraer repositorio.
 */
final class ProcesarExploracionHandler implements CommandHandler
{
    private const MINUTOS_POR_ENCUENTRO = 3;

    private readonly ?Closure $aleatorio;

    /**
     * @param  callable():float|null  $aleatorio  Proveedor determinista [0,1)
     *                                            para TEST (seam). null → mt_rand.
     */
    public function __construct(
        private readonly CommandBus $bus,
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

        $retirada = $this->procesarTick($exploracion, $eventos, $desde, $hasta);

        $completada = $command->forzarRegreso
            || ($inicioVuelta !== null && now()->greaterThanOrEqualTo($inicioVuelta))
            || $retirada;

        if ($completada) {
            $this->bus->dispatch(new FinalizarExploracionCommand($exploracion));
        }

        return null;
    }

    /**
     * Genera y resuelve los eventos del tick (RF-05): acumula duration_loss,
     * adelanta ultimo_procesado al futuro (D2) y detecta retirada.
     *
     * @param  Collection<string, mixed>  $eventos
     */
    private function procesarTick(
        ExploracionActiva $exploracion,
        Collection $eventos,
        CarbonInterface $desde,
        CarbonInterface $hasta,
    ): bool {
        $retirada = false;

        if (! $hasta->greaterThan($desde)) {
            $eventos->put('ultimo_procesado', $hasta->toIso8601String());
            $exploracion->eventos = $eventos;
            $exploracion->save();

            return false;
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

            return false;
        }

        $aleatorio = $this->aleatorio ?? static fn (): float => mt_rand(1, 100) / 100;
        $contexto = $this->contextoEvaluacion($exploracion);

        $resueltos = [];
        $perdidoTick = 0;
        foreach ($nuevos as $evento) {
            $resuelto = $this->resolverEvento($evento, $contexto, $aleatorio);
            $resueltos[] = $resuelto;
            $perdidoTick += $resuelto['duration_loss'] ?? 0;

            if (($resuelto['resolucion'] ?? '') === 'retirada') {
                $retirada = true;
            }

            if (($resuelto['retirada_probable'] ?? false) === true && $aleatorio() < 0.5) {
                $retirada = true;
            }
        }

        $perdidoTick = $this->aplicarReduccionTiempo($perdidoTick, $contexto['roles']);

        $bitacora = $eventos->get('bitacora', []);
        $eventos->put('bitacora', [...$bitacora, ...$resueltos]);
        $eventos->put('tiempo_perdido', (int) $eventos->get('tiempo_perdido', 0) + $perdidoTick);

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

        return $retirada;
    }

    /**
     * Contexto de evaluación: peligro del hábitat, roles, capacidad del equipo
     * (base stats + afinidad + rol + sinergia) y detección de emboscadas.
     *
     * @return array{
     *     peligro: int,
     *     roles: list<RolExploracion>,
     *     capacidad: int,
     *     detectaEmboscadas: bool,
     * }
     */
    private function contextoEvaluacion(ExploracionActiva $exploracion): array
    {
        $habitat = $exploracion->habitat;
        $peligro = $habitat !== null ? max(1, $habitat->peligro ?? 1) : 1;

        $pool = $this->poolHabitat($exploracion);
        $tiposPool = $this->tiposDelPool($pool);

        $equipo = $exploracion->team;
        $roles = [];
        $capacidades = [];
        $detectaEmboscadas = false;

        foreach ($equipo->members ?? [] as $miembro) {
            $reclutado = $miembro->reclutado;
            $pokemon = $reclutado?->pokemon;
            if ($pokemon === null) {
                continue;
            }

            $rol = RolExploracion::tryFrom($miembro->behavior ?? '') ?? RolExploracion::COMBATIENTE;
            $roles[] = $rol;

            if ($rol->detectaEmboscadas()) {
                $detectaEmboscadas = true;
            }

            $tipos = $this->tiposDe($pokemon);
            $enPool = in_array($pokemon->id, array_column($pool, 'id'), true);
            $base = CalculadorCapacidadEquipo::baseDeStats($pokemon->stats->pluck('base_stat')->all());
            $afinidad = CalculadorCapacidadEquipo::afinidadDeMiembro($tipos, $tiposPool, $enPool);

            $capacidades[] = CalculadorCapacidadEquipo::capacidadMiembro(
                base: $base,
                afinidad: $afinidad,
                bonusRol: $rol->bonusCapacidad(),
                bonusSinergia: 0,
            );
        }

        $capacidad = CalculadorCapacidadEquipo::capacidadEquipo($capacidades);

        $sinergia = SinergiaEquipo::sinergiaPara($roles);
        if ($sinergia !== null) {
            $capacidad = max(0, $capacidad + $sinergia['bonusCapacidad']);
            if ($sinergia['detectaEmboscadas']) {
                $detectaEmboscadas = true;
            }
        }

        return [
            'peligro' => $peligro,
            'roles' => $roles,
            'capacidad' => $capacidad,
            'detectaEmboscadas' => $detectaEmboscadas,
        ];
    }

    /**
     * @param  array<string, mixed>  $evento
     * @param  array{
     *     peligro: int,
     *     roles: list<RolExploracion>,
     *     capacidad: int,
     *     detectaEmboscadas: bool,
     * }  $contexto
     * @return array<string, mixed>
     */
    private function resolverEvento(array $evento, array $contexto, callable $aleatorio): array
    {
        $tipo = $evento['tipo'] ?? '';

        if ($tipo === 'encuentro') {
            $resolucion = EvaluadorExploracion::resolverEncuentro(
                subtipo: (string) ($evento['subtype'] ?? 'normal'),
                capacidad: $contexto['capacidad'],
                peligro: $contexto['peligro'],
                aleatorio: $aleatorio,
                roles: $contexto['roles'],
            );

            return array_merge($evento, $resolucion);
        }

        if ($tipo === 'emboscada') {
            $resolucion = EvaluadorExploracion::resolverEmboscada(
                detectadaPorVanguardia: $contexto['detectaEmboscadas'],
                capacidad: $contexto['capacidad'],
                peligro: $contexto['peligro'],
                aleatorio: $aleatorio,
                roles: $contexto['roles'],
            );

            return array_merge($evento, $resolucion);
        }

        if ($tipo === 'contratiempo') {
            $resolucion = EvaluadorExploracion::resolverContratiempo(
                subtipo: (string) ($evento['subtype'] ?? 'terreno'),
                roles: $contexto['roles'],
            );

            return array_merge($evento, $resolucion);
        }

        return $evento;
    }

    /**
     * Rastreador: −50 % de tiempo perdido general (se aplica al total del tick).
     *
     * @param  list<RolExploracion>  $roles
     */
    private function aplicarReduccionTiempo(int $perdido, array $roles): int
    {
        $multiplicador = 1.0;
        foreach ($roles as $rol) {
            $multiplicador *= $rol->multiplicadorTiempoPerdido();
        }

        return (int) floor($perdido * $multiplicador);
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
     * exploración, con sus tipos (para afinidad) y stats con effort>0
     * (para caramelos EV restringidos al pool).
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

    /**
     * @param  array<int, array{id: int, capture_rate: int, hatch: int|null, tipos: list<TipoPokemon>}>  $pool
     * @return list<TipoPokemon>
     */
    private function tiposDelPool(array $pool): array
    {
        $tipos = [];
        foreach ($pool as $pokemon) {
            foreach ($pokemon['tipos'] as $tipo) {
                if (! in_array($tipo, $tipos, true)) {
                    $tipos[] = $tipo;
                }
            }
        }

        return $tipos;
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
