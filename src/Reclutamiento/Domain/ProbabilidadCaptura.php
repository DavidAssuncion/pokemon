<?php

declare(strict_types=1);

namespace Src\Reclutamiento\Domain;

final class ProbabilidadCaptura
{
    public const TASA_BASE = 45;

    /** Probabilidad [0,1] de captura según capture_rate (regla: capture_rate/255, default 45). */
    public static function probabilidad(int $captureRate): float
    {
        return min(1.0, ($captureRate > 0 ? $captureRate : self::TASA_BASE) / 255);
    }

    /** Roll de captura con aleatorio inyectable [0,1) — decidir si captura. */
    public static function intentar(int $captureRate, callable $aleatorio): bool
    {
        return $aleatorio() <= self::probabilidad($captureRate);
    }
}
