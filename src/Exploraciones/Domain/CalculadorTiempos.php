<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

use Carbon\CarbonInterface;

final class CalculadorTiempos
{
    /** Momento en que el equipo inicia la vuelta: fin − (duración_total / 4). */
    public static function inicioVuelta(CarbonInterface $inicio, CarbonInterface $fin): CarbonInterface
    {
        $duracionMinutos = max(0, (int) abs($fin->diffInMinutes($inicio)));

        return $fin->copy()->subMinutes(intdiv($duracionMinutos, 4));
    }

    /** Progreso 0-100 del tiempo transcurrido entre inicio y fin. */
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
