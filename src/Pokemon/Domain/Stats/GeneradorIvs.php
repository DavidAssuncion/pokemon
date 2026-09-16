<?php

declare(strict_types=1);

namespace Src\Pokemon\Domain\Stats;

/**
 * Generador de IVs (Individual Values) para reclutados.
 * IVs van de 0 a 15; se almacenan como float en StatsValue.
 */
final class GeneradorIvs
{
    /**
     * IVs aleatorios 0-15 por stat (reclutamiento normal, encuentro salvaje no-shiny).
     * aleatorio: callable(): float returning [0, 1).
     */
    public static function aleatorios(?callable $aleatorio = null): StatsValue
    {
        return self::generar(0, 15, $aleatorio);
    }

    /**
     * IVs máximos (15/15/15/15/15/15): captura shiny salvaje + huevos shiny.
     */
    public static function maximos(): StatsValue
    {
        return new StatsValue(hp: 15.0, attack: 15.0, defense: 15.0, spAtk: 15.0, spDef: 15.0, speed: 15.0);
    }

    /**
     * IVs de huevo: 10-15 por stat (mínimo 10 por spec).
     * aleatorio: callable(): float returning [0, 1).
     */
    public static function huevo(?callable $aleatorio = null): StatsValue
    {
        return self::generar(10, 15, $aleatorio);
    }

    /**
     * Genera 6 IVs dentro del rango [min, max]. Fuente aleatoria opcional
     * inyectable (tests); por defecto usa random_int determinista.
     *
     * @param  callable(): float|null  $aleatorio  devuelve [0, 1)
     */
    private static function generar(int $min, int $max, ?callable $aleatorio): StatsValue
    {
        if ($aleatorio !== null) {
            $iv = static fn (): float => min((float) $max, (float) (int) ($min + $aleatorio() * ($max - $min + 1)));
        } else {
            $iv = static fn (): float => (float) random_int($min, $max);
        }

        return new StatsValue(
            hp: $iv(),
            attack: $iv(),
            defense: $iv(),
            spAtk: $iv(),
            spDef: $iv(),
            speed: $iv(),
        );
    }
}
