<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

use Carbon\CarbonInterface;

/**
 * Cálculos de tiempos de una vuelta de exploración (dominio puro).
 *
 * Sustituye a CalculadorTiempos (eliminado en el refactor F1-F9): la fórmula
 * de inicioVuelta estaba duplicada en PresentadorExploraciones y
 * ProcesarExploracionHandler; este helper centraliza ambas versiones.
 */
final class CalculadorVueltaExploracion
{
    /**
     * Momento en que el explorador inicia la vuelta: fin − (duración/4).
     * null si la exploración no tiene fin (indefinida). Con duración 0
     * devuelve el mismo momento que fin.
     */
    public static function inicioVuelta(CarbonInterface $inicio, ?CarbonInterface $fin): ?CarbonInterface
    {
        if ($fin === null) {
            return null;
        }

        $duracionMinutos = max(0, (int) abs($fin->diffInMinutes($inicio)));

        return $fin->copy()->subMinutes(intdiv($duracionMinutos, 4));
    }

    /**
     * Progreso 0-100 del tiempo transcurrido entre inicio y fin, clampeado
     * a 100 si ahora supera el fin. Duración 0 → 100 (ya inició la vuelta).
     */
    public static function progreso(CarbonInterface $inicio, CarbonInterface $fin, CarbonInterface $ahora): int
    {
        $total = (int) abs($fin->diffInMinutes($inicio));
        if ($total <= 0) {
            return 100;
        }

        $transcurrido = max(0, (int) abs($ahora->diffInMinutes($inicio)));

        return max(0, min(100, (int) round($transcurrido / $total * 100)));
    }
}
