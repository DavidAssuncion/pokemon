<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\Tiempos;

use Src\Exploraciones\Domain\CalculadorVueltaExploracion;

/**
 * Cálculos puros de tiempo de una expedición.
 */
final class CalculadoraDuracionExploracion
{
    /**
     * Duración real: nominal (minutos entre inicio y fin) menos el tiempo
     * perdido acumulado, mínimo 0 (RF-05).
     */
    public function duracionReal(VentanaExploracion $ventana, int $tiempoPerdido): int
    {
        if ($ventana->fin === null) {
            return 0;
        }

        $nominal = max(0, (int) abs($ventana->fin->diffInMinutes($ventana->inicio)));

        return max(0, $nominal - $tiempoPerdido);
    }

    /**
     * Fracción del botín nominal que corresponde al tiempo efectivo de la
     * expedición. La base es el inicio de la vuelta (75 % de la duración);
     * una expedición indefinida usa 60 minutos. El resultado se acota a [0, 1].
     */
    public function factorTiempo(VentanaExploracion $ventana): float
    {
        $inicioVuelta = CalculadorVueltaExploracion::inicioVuelta($ventana->inicio, $ventana->fin);
        $base = $inicioVuelta !== null
            ? max(1, (int) abs($inicioVuelta->diffInMinutes($ventana->inicio)))
            : 60;
        $minutos = max(0, (int) abs($ventana->inicio->diffInMinutes($ventana->ahora)));

        return min(1.0, $minutos / $base);
    }
}
