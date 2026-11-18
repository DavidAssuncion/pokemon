<?php

declare(strict_types=1);

namespace Src\Pokemon\Domain\Stats;

/**
 * Suma los bonos de las piezas de forja equipadas a los stats base.
 *
 * Dominio puro y sin dependencia de Forja: recibe los stats base y los bonos
 * ya agregados. Sin bonos (`null`) devuelve la misma instancia de base, de
 * modo que el comportamiento previo al equipamiento no cambia.
 *
 * Dos entradas con el mismo contrato: `StatsValue` (CP) y `DatosStats`
 * (motor de batalla).
 */
final class AplicadorBonusPiezas
{
    public static function aplicar(StatsValue $base, ?StatsValue $bonos = null): StatsValue
    {
        if ($bonos === null) {
            return $base;
        }

        return new StatsValue(
            hp: ($base->hp ?? 0.0) + ($bonos->hp ?? 0.0),
            attack: ($base->attack ?? 0.0) + ($bonos->attack ?? 0.0),
            defense: ($base->defense ?? 0.0) + ($bonos->defense ?? 0.0),
            spAtk: ($base->spAtk ?? 0.0) + ($bonos->spAtk ?? 0.0),
            spDef: ($base->spDef ?? 0.0) + ($bonos->spDef ?? 0.0),
            speed: ($base->speed ?? 0.0) + ($bonos->speed ?? 0.0),
        );
    }

    public static function aplicarADatos(DatosStats $base, ?StatsValue $bonos = null): DatosStats
    {
        if ($bonos === null) {
            return $base;
        }

        return new DatosStats(
            hp: self::sumar($base->hp, $bonos->hp),
            atk: self::sumar($base->atk, $bonos->attack),
            def: self::sumar($base->def, $bonos->defense),
            spAtk: self::sumar($base->spAtk, $bonos->spAtk),
            spDef: self::sumar($base->spDef, $bonos->spDef),
            speed: self::sumar($base->speed, $bonos->speed),
        );
    }

    private static function sumar(int $base, ?float $bono): int
    {
        return (int) round($base + ($bono ?? 0.0));
    }
}
