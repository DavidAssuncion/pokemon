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
        if ($aleatorio !== null) {
            $iv = static fn (): float => min(15.0, (float) (int) ($aleatorio() * 16));
        } else {
            $iv = static fn (): float => (float) random_int(0, 15);
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
        if ($aleatorio !== null) {
            $iv = static fn (): float => min(15.0, (float) (int) (10 + $aleatorio() * 6));
        } else {
            $iv = static fn (): float => (float) random_int(10, 15);
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
