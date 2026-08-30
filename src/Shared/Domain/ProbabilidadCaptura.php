<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

/**
 * Regla unificada de probabilidad de captura (cap-45), compartida entre
 * Reclutamiento (ServicioCaptura) y Exploraciones (FinalizarExploracionHandler).
 *
 * chance = min(tasa, 45) / 255  con  tasa = captureRate > 0 ? captureRate : 45
 * → máximo 17.6% (45/255) por derrota, independientemente del capture_rate.
 */
final class ProbabilidadCaptura
{
    public const TASA_BASE = 45;
    public const CAP = 45;
    public const MAXIMO = 255;

    /** Probabilidad [0, 45/255] de captura según capture_rate (regla cap-45). */
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
