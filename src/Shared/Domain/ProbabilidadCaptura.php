<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

/**
 * Regla unificada de probabilidad de captura (cap-25), compartida entre
 * Reclutamiento (ServicioCaptura) y Exploraciones (FinalizarExploracionHandler).
 *
 * chance = min(tasa, 25) / 255  con  tasa = captureRate > 0 ? captureRate : 25
 * → máximo 9.8% (25/255) por derrota, independientemente del capture_rate.
 */
final class ProbabilidadCaptura
{
    public const TASA_BASE = 25;
    public const CAP = 25;
    public const MAXIMO = 255;

    /** Probabilidad [0, 25/255] de captura según capture_rate (regla cap-25). */
    public static function probabilidad(int $captureRate): float
    {
        $tasa = $captureRate > 0 ? $captureRate : self::TASA_BASE;

        return min($tasa, self::CAP) / self::MAXIMO;
    }

    /**
     * Probabilidad con multiplicador de granja/refugio. Se aplica DESPUÉS del
     * cap-25 para que los refugios sigan teniendo efecto con capture_rate alto.
     */
    public static function probabilidadConFactor(int $captureRate, float $factor = 1.0): float
    {
        return max(0.0, min(1.0, self::probabilidad($captureRate) * max(0.0, $factor)));
    }

    /** Roll de captura con aleatorio inyectable [0,1) — decidir si captura. */
    public static function intentar(int $captureRate, callable $aleatorio): bool
    {
        return $aleatorio() <= self::probabilidad($captureRate);
    }

    /**
     * Roll de captura con multiplicador (refugio). El factor se aplica a la
     * probabilidad final, no al capture_rate, para no quedar topado por cap-25.
     */
    public static function intentarConFactor(int $captureRate, float $factor, callable $aleatorio): bool
    {
        return $aleatorio() <= self::probabilidadConFactor($captureRate, $factor);
    }
}
