<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Calcula el fin de una exploración (dominio puro, sin Eloquent):
 * - hora_limite → hoy a esa hora (Carbon::today()->setTimeFromTimeString).
 * - duracion_horas → inicio + N horas.
 * - null → exploración indefinida (sin fin).
 *
 * Duplicaba la misma lógica en ProcesarExploracionHandler,
 * FinalizarExploracionHandler y PresentadorExploraciones.
 */
final class CalculadorFinExploracion
{
    public static function calcular(?string $horaLimite, ?int $duracionHoras, CarbonInterface $inicio): ?CarbonInterface
    {
        if ($horaLimite !== null) {
            return Carbon::today()->setTimeFromTimeString($horaLimite);
        }

        if ($duracionHoras !== null) {
            return $inicio->copy()->addHours($duracionHoras);
        }

        return null;
    }
}
