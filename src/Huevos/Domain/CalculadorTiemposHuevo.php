<?php

declare(strict_types=1);

namespace Src\Huevos\Domain;

/**
 * Calcula tiempos de gestación y eclosión a partir del capture_rate y el hatch.
 *
 * Contrato aprobado por el cliente (B1):
 *   puesta_horas    = 255 / capture_rate
 *   eclosion_horas  = ceil(hatch / 15)   (DIVISOR_HATCH)
 *   total_horas     = puesta_horas + eclosion_horas
 *
 * La respuesta del frontend trabaja en horas (float): tiempo_total_horas,
 * tiempo_restante y progreso comparten la misma unidad.
 */
final class CalculadorTiemposHuevo
{
    public const PUESTA_HORAS_BASE = 255;

    public const DIVISOR_HATCH = 15;

    public static function puestaHoras(int $captureRate): float
    {
        return (float) (self::PUESTA_HORAS_BASE / max($captureRate, 1));
    }

    public static function eclosionHoras(int $hatch): float
    {
        return ceil($hatch / self::DIVISOR_HATCH);
    }

    public static function sumaHoras(int $captureRate, int $hatch): float
    {
        return self::puestaHoras($captureRate) + self::eclosionHoras($hatch);
    }
}
