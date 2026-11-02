<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

/**
 * Reglas únicas del tope de EV (255) y del valor de un caramelo de EV.
 *
 * Los EV superiores a 255 son legado: se conservan intactos y no se reducen
 * al aplicar nuevas ganancias. Ninguna fuente puede superar el tope desde 0.
 */
final class ReglasEv
{
    public const TOPE = 255;

    public const EV_POR_CARAMELO = 10;

    /**
     * Nº de caramelos a consumir sin superar el tope. El último caramelo puede
     * pasarse del tope y se recorta en {@see self::aplicar()}.
     */
    public static function caramelosHastaTope(float $evActual, int $caramelosDisponibles): int
    {
        $restante = max(0.0, self::TOPE - $evActual);
        $necesarios = (int) ceil($restante / self::EV_POR_CARAMELO);

        return min(max(0, $caramelosDisponibles), $necesarios);
    }

    /**
     * Nuevo valor de EV tras aplicar caramelos. Si el valor actual ya alcanza
     * el tope (incluido legado >255), se devuelve sin cambios.
     */
    public static function aplicar(float $evActual, int $caramelos): float
    {
        if ($evActual >= self::TOPE) {
            return $evActual;
        }

        return (float) min(self::TOPE, $evActual + $caramelos * self::EV_POR_CARAMELO);
    }
}
