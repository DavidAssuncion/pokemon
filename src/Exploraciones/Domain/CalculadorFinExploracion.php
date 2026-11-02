<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

use Carbon\CarbonInterface;

/**
 * Calcula el fin de una exploración (dominio puro, sin Eloquent):
 * - hora_limite → siguiente ocurrencia de esa hora desde el inicio: si la hora
 *   es posterior al inicio, el mismo día; si es igual o anterior, al día
 *   siguiente (p. ej. inicio 23:00, hora_limite 01:00 → mañana 01:00).
 * - duracion_horas → inicio + N horas.
 * - null → exploración indefinida (sin fin).
 *
 * La hora se interpreta siempre relativa al inicio, nunca a "hoy": un
 * `Carbon::today()` producía fines en el pasado con horas nocturnas.
 *
 * Duplicaba la misma lógica en ProcesarExploracionHandler,
 * FinalizarExploracionHandler y PresentadorExploraciones.
 */
final class CalculadorFinExploracion
{
    public static function calcular(?string $horaLimite, ?int $duracionHoras, CarbonInterface $inicio): ?CarbonInterface
    {
        if ($horaLimite !== null) {
            $fin = $inicio->copy()->setTimeFromTimeString($horaLimite);

            if ($fin->lessThanOrEqualTo($inicio)) {
                $fin->addDay();
            }

            return $fin;
        }

        if ($duracionHoras !== null) {
            return $inicio->copy()->addHours($duracionHoras);
        }

        return null;
    }
}
