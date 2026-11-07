<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Ejecucion;

/**
 * Única fuente de las fórmulas de escudo y curación del modo aliado.
 *
 * La usan tanto el ejecutor real como la presentación para evitar que el
 * preview y el resultado difieran por redondeo.
 */
final class CalculadoraCuracionAliada
{
    private const DIVISOR_RECURSO = 32;

    private const FACTOR_ESCUDO = 0.10;

    private const DECIMALES = 9;

    private const MINIMO = 1;

    /**
     * Incremento de escudo (físico o especial) al usar un movimiento de apoyo.
     */
    public function incrementoEscudo(float $maxEscudo, float $statLanzador): int
    {
        $incremento = $maxEscudo / self::DIVISOR_RECURSO + $statLanzador * self::FACTOR_ESCUDO;

        return max(self::MINIMO, (int) floor(round($incremento, self::DECIMALES)));
    }

    /**
     * Curación de PS con un aporte fijo ya calculado (Acua Aro aliado).
     */
    public function curacionConAporteFijo(float $psMaxReceptor, float $aporteFijo): int
    {
        $curacion = $psMaxReceptor / self::DIVISOR_RECURSO + $aporteFijo;

        return max(self::MINIMO, (int) floor(round($curacion, self::DECIMALES)));
    }
}
