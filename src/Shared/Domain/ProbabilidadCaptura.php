<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

/**
 * Regla unificada de probabilidad de captura (plan v5 §4.3), compartida entre
 * Reclutamiento (ServicioCaptura), Combate de ruta (OtorgarRecompensasRuta) y
 * Exploraciones (FinalizarExploracionHandler).
 *
 * pBase = min(0.25, (tasa / 255) × factorHabitat)
 *   con tasa = captureRate > 0 ? captureRate : 25
 *   y factorHabitat 1,0 / 0,5 / 0,3 para hábitat 1/2/3.
 *
 * p = min(0.30, pBase × factorRefugio × factorSenda)
 *
 * El cap base es el 25 % y la única excepción es el cap de Senda (30 %).
 * Los factores se aplican siempre sobre la probabilidad final, nunca sobre el
 * capture_rate, para que refugio y Senda sigan teniendo efecto.
 */
final class ProbabilidadCaptura
{
    public const TASA_BASE = 25;
    public const CAP = 25;
    public const MAXIMO = 255;
    public const BASE_MAXIMA = 0.25;
    public const CAP_SENDA = 0.30;

    /**
     * Probabilidad base [0, 0.25] según capture_rate y factor de hábitat.
     * Un captureRate no positivo cae a {@see self::TASA_BASE}.
     */
    public static function probabilidadBase(int $captureRate, float $factorHabitat = 1.0): float
    {
        $tasa = $captureRate > 0 ? $captureRate : self::TASA_BASE;

        return max(0.0, min(self::BASE_MAXIMA, ($tasa / self::MAXIMO) * max(0.0, $factorHabitat)));
    }

    /** Probabilidad base del hábitat 1 (sin penalización). */
    public static function probabilidad(int $captureRate): float
    {
        return self::probabilidadBase($captureRate);
    }

    /**
     * Probabilidad base con un multiplicador (refugio o Senda). Se aplica
     * DESPUÉS del cap base para no quedar topado por el 25 %, y se topa en
     * {@see self::CAP_SENDA} (30 %).
     */
    public static function probabilidadConFactor(int $captureRate, float $factor = 1.0): float
    {
        return max(0.0, min(self::CAP_SENDA, self::probabilidadBase($captureRate) * max(0.0, $factor)));
    }

    /** Probabilidad final con hábitat, refugio y Senda (plan v5 §4.3). */
    public static function probabilidadConFactores(
        int $captureRate,
        float $factorHabitat,
        float $factorRefugio,
        float $factorSenda = 1.0,
    ): float {
        $base = self::probabilidadBase($captureRate, $factorHabitat);

        return max(0.0, min(
            self::CAP_SENDA,
            $base * max(0.0, $factorRefugio) * max(0.0, $factorSenda),
        ));
    }

    /** Roll de captura con aleatorio inyectable [0,1) — decidir si captura. */
    public static function intentar(int $captureRate, callable $aleatorio): bool
    {
        return $aleatorio() <= self::probabilidad($captureRate);
    }

    /**
     * Roll de captura con multiplicador (refugio o Senda). El factor se aplica
     * a la probabilidad final, no al capture_rate.
     */
    public static function intentarConFactor(int $captureRate, float $factor, callable $aleatorio): bool
    {
        return $aleatorio() <= self::probabilidadConFactor($captureRate, $factor);
    }

    /**
     * Roll de captura con hábitat, refugio y Senda. El callable va antes del
     * factor de Senda para conservar los seams de test existentes.
     */
    public static function intentarConFactores(
        int $captureRate,
        float $factorHabitat,
        float $factorRefugio,
        callable $aleatorio,
        float $factorSenda = 1.0,
    ): bool {
        return $aleatorio() <= self::probabilidadConFactores(
            $captureRate,
            $factorHabitat,
            $factorRefugio,
            $factorSenda,
        );
    }
}
