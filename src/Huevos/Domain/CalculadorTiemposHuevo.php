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

    /** Cada nivel de nido reduce un 10 % el tiempo, hasta un 50 % a nivel 5. */
    public const REDUCCION_POR_NIVEL = 0.10;

    public const REDUCCION_MAXIMA = 0.50;

    public static function puestaHoras(int $captureRate, int $nidoNivel = 0): float
    {
        return (float) (self::PUESTA_HORAS_BASE / max($captureRate, 1)) * self::factorNido($nidoNivel);
    }

    public static function eclosionHoras(int $hatch, int $nidoNivel = 0): float
    {
        return ceil(max($hatch, 1) / self::DIVISOR_HATCH) * self::factorNido($nidoNivel);
    }

    public static function sumaHoras(int $captureRate, int $hatch, int $nidoNivel = 0): float
    {
        return self::puestaHoras($captureRate, $nidoNivel) + self::eclosionHoras($hatch, $nidoNivel);
    }

    /**
     * Factor multiplicador de tiempo: 1.0 sin nido, 0.9 a nivel 1 ... 0.5 a nivel 5.
     */
    public static function factorNido(int $nidoNivel): float
    {
        $nivel = max(0, min(5, $nidoNivel));

        return 1.0 - min(self::REDUCCION_MAXIMA, self::REDUCCION_POR_NIVEL * $nivel);
    }
}
