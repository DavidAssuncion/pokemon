<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

use Carbon\CarbonInterface;

/**
 * Calcula el porcentaje de progreso de una expedición (dominio puro).
 *
 * Sin fechas válidas o con un intervalo nulo/negativo el progreso es 0. Si
 * `ahora` alcanza `fin` el progreso es 100. El resultado siempre queda
 * acotado al rango 0..100.
 */
final class CalculadorProgresoExpedicion
{
    private const PROGRESO_MINIMO = 0;
    private const PROGRESO_MAXIMO = 100;

    public static function calcular(
        ?CarbonInterface $inicio,
        ?CarbonInterface $fin,
        CarbonInterface $ahora,
    ): int {
        if ($inicio === null || $fin === null) {
            return self::PROGRESO_MINIMO;
        }

        $total = $fin->getTimestamp() - $inicio->getTimestamp();

        if ($total <= 0) {
            return self::PROGRESO_MINIMO;
        }

        if ($ahora->getTimestamp() >= $fin->getTimestamp()) {
            return self::PROGRESO_MAXIMO;
        }

        $transcurrido = $ahora->getTimestamp() - $inicio->getTimestamp();
        $progreso = (int) round(($transcurrido / $total) * self::PROGRESO_MAXIMO);

        return max(self::PROGRESO_MINIMO, min(self::PROGRESO_MAXIMO, $progreso));
    }
}
