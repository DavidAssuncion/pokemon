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

    /** Roll de captura con aleatorio inyectable [0,1) — decidir si captura. */
    public static function intentar(int $captureRate, callable $aleatorio): bool
    {
        return $aleatorio() <= self::probabilidad($captureRate);
    }
}
