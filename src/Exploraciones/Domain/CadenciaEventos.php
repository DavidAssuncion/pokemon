<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

use Carbon\CarbonInterface;
use DateTimeInterface;

/**
 * Cadencia de eventos de exploración por tiempo efectivo transcurrido.
 *
 * Los eventos se programan según el tiempo real de exploración (inicio → hasta
 * menos el tiempo perdido en descansos y contratiempos), no según el hueco
 * entre ticks de refresco. El contador `eventos_generados` evita volver a
 * programar eventos ya programados y el tope limita el trabajo por procesamiento.
 */
final class CadenciaEventos
{
    public const MAX_EVENTOS_POR_PROCESAMIENTO = 12;

    /**
     * Eventos que faltan por programar en la ventana [inicio, hasta].
     *
     * @param  CarbonInterface|DateTimeInterface  $inicio  Inicio de la exploración.
     * @param  CarbonInterface|DateTimeInterface  $hasta  Límite del procesamiento actual.
     * @param  int  $eventosGenerados  Eventos ya programados en ticks anteriores.
     * @param  int  $tiempoPerdidoMinutos  Minutos de descanso/contratiempo acumulados.
     * @param  int  $intervaloBaseMinutos  Intervalo base entre eventos.
     * @param  float  $reduccionIntervalo  Reducción por movilidad (0..1).
     * @param  int  $bonusEventos  Bonus de eventos por rango de exploración.
     */
    public static function eventosPendientes(
        CarbonInterface|DateTimeInterface $inicio,
        CarbonInterface|DateTimeInterface $hasta,
        int $eventosGenerados,
        int $tiempoPerdidoMinutos,
        int $intervaloBaseMinutos,
        float $reduccionIntervalo,
        int $bonusEventos,
    ): int {
        $esperados = self::esperados(
            $inicio,
            $hasta,
            $tiempoPerdidoMinutos,
            $intervaloBaseMinutos,
            $reduccionIntervalo,
            $bonusEventos,
        );

        return min(
            self::MAX_EVENTOS_POR_PROCESAMIENTO,
            max(0, $esperados - $eventosGenerados),
        );
    }

    /**
     * Eventos que corresponden al reloj en la ventana [inicio, hasta], sin
     * descontar los ya generados ni aplicar el tope por procesamiento.
     *
     * Se usa también para el backfill del contador de exploraciones antiguas:
     * al no existir `eventos_generados` se acota el valor inicial a
     * `min(esperados, eventos ya registrados en la bitácora)`.
     *
     * @param  CarbonInterface|DateTimeInterface  $inicio  Inicio de la exploración.
     * @param  CarbonInterface|DateTimeInterface  $hasta  Límite del procesamiento actual.
     * @param  int  $tiempoPerdidoMinutos  Minutos de descanso/contratiempo acumulados.
     * @param  int  $intervaloBaseMinutos  Intervalo base entre eventos.
     * @param  float  $reduccionIntervalo  Reducción por movilidad (0..1).
     * @param  int  $bonusEventos  Bonus de eventos por rango de exploración.
     */
    public static function esperados(
        CarbonInterface|DateTimeInterface $inicio,
        CarbonInterface|DateTimeInterface $hasta,
        int $tiempoPerdidoMinutos,
        int $intervaloBaseMinutos,
        float $reduccionIntervalo,
        int $bonusEventos,
    ): int {
        if ($hasta->getTimestamp() <= $inicio->getTimestamp()) {
            return 0;
        }

        $minutosTranscurridos = intdiv($hasta->getTimestamp() - $inicio->getTimestamp(), 60);
        $minutosEfectivos = max(0, $minutosTranscurridos - $tiempoPerdidoMinutos);

        $intervalo = max(
            1,
            (int) floor($intervaloBaseMinutos * (1 - $reduccionIntervalo) / (1 + $bonusEventos)),
        );

        return intdiv($minutosEfectivos, $intervalo);
    }
}
