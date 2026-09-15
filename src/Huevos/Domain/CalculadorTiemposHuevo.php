<?php

declare(strict_types=1);

namespace Src\Huevos\Domain;

/**
 * Calcula tiempos de gestación y eclosión a partir del capture_rate del Pokémon base.
 *
 * fórmula:
 *   gestacion_min  = clamp(round(7200 / capture_rate), 15, 240)
 *   eclosion_min   = gestacion_min × 2
 *   total_horas    = (gestacion + eclosion) / 60
 */
final class CalculadorTiemposHuevo
{
    public const GESTACION_MIN_MINUTOS = 15;
    public const GESTACION_MAX_MINUTOS = 240;
    public const INCUBACION_FACTOR = 2.0;

    public static function gestacionMinutos(int $captureRate): int
    {
        $tasa = $captureRate > 0 ? $captureRate : 45;

        return max(self::GESTACION_MIN_MINUTOS, min(self::GESTACION_MAX_MINUTOS, (int) round(7200 / $tasa)));
    }

    public static function eclosionMinutos(int $captureRate): int
    {
        return (int) round(self::gestacionMinutos($captureRate) * self::INCUBACION_FACTOR);
    }

    public static function totalMinutos(int $captureRate): int
    {
        return self::gestacionMinutos($captureRate) + self::eclosionMinutos($captureRate);
    }
}
