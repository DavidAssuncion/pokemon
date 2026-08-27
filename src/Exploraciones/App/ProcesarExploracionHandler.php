<?php

declare(strict_types=1);

namespace Src\Exploraciones\App;

use App\Models\ExploracionActiva;
use App\Models\Pokemon;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use LogicException;
use Src\Exploraciones\Domain\SimuladorEncuentros;
use Src\Shared\Bus\Command;
use Src\Shared\Bus\CommandBus;
use Src\Shared\Bus\CommandHandler;

/**
 * Tick de exploración: genera encuentros pendientes y, si toca, despacha
 * FinalizarExploracionCommand a través del bus.
 *
 * @todo Excepción temporal a la regla de dependencias: src/ importa
 *       App\Models/App\Jobs/Illuminate (deuda WIP). Ticket v2: extraer
 *       repositorio/interfaz en Domain o mover handlers a app/.
 */
final class ProcesarExploracionHandler implements CommandHandler
{
    private const MINUTOS_POR_ENCUENTRO = 5;

    public function __construct(
        private readonly CommandBus $bus,
    ) {
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

        $eventos = $exploracion->eventos ?? [];
        $desde = $this->ultimoProcesado($eventos) ?? $inicio;
        $hasta = $this->limiteTick(now(), $fin, $inicioVuelta);

        if ($hasta->greaterThan($desde)) {
            $nuevos = SimuladorEncuentros::generarEventos(
                SimuladorEncuentros::poolPonderado($this->poolHabitat($exploracion)),
                intdiv((int) abs($hasta->diffInMinutes($desde)), self::MINUTOS_POR_ENCUENTRO),
                $desde,
                $hasta,
            );

            if ($nuevos !== []) {
                $bitacora = $eventos['bitacora'] ?? [];
                $eventos['bitacora'] = [...$bitacora, ...$nuevos];
            }
        }

        $eventos['ultimo_procesado'] = $hasta->toIso8601String();
        $exploracion->update(['eventos' => $eventos]);

        $completada = $command->forzarRegreso
            || ($inicioVuelta !== null && now()->greaterThanOrEqualTo($inicioVuelta));

        if ($completada) {
            $this->bus->dispatch(new FinalizarExploracionCommand($exploracion));
        }

        return null;
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
     * @param  array<string, mixed>  $eventos
     */
    private function ultimoProcesado(array $eventos): ?CarbonInterface
    {
        $ultimo = $eventos['ultimo_procesado'] ?? null;

        return is_string($ultimo) ? Carbon::parse($ultimo) : null;
    }

    /**
     * Pool de encuentros: pokémon del hábitat asignados al nivel de la exploración.
     *
     * @return array<int, array{id: int, capture_rate: int, hatch: int|null}>
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
            ->map(fn (Pokemon $pokemon) => [
                'id' => $pokemon->id,
                'capture_rate' => $pokemon->capture_rate,
                'hatch' => $pokemon->hatch,
            ])
            ->values()
            ->all();
    }
}
