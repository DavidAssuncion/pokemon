<?php

declare(strict_types=1);

namespace Src\Pokemon\Domain\Stats;

/**
 * Calcula el Combat Power (CP) de un pokémon a partir de sus BattleStats
 * (stats ya calculados a nivel) y un índice de esfuerzo (EV).
 *
 * Fórmula:
 *   CP = floor(((HP+Atk+Def+SAtk+SDef+Spd) * Level * 6 / 100) + (EV * ((Level / 4) / 100 + 2)))
 *
 * - El término de stats ya incorpora los EVs/IVs aplicados por BattleStats.
 * - El segundo término es el índice de esfuerzo agregado del rival/reclutado.
 * - Nunca devuelve un valor negativo.
 */
final class CalculadorCP
{
    private const FACTOR_STATS = 6;

    private const DIVISOR = 100;

    private const DIVISOR_NIVEL_EV = 4;

    private const BASE_EV = 2;

    public static function calcular(BattleStats $stats, int $ev): int
    {
        $sumaStats = $stats->hp
            + $stats->attack
            + $stats->defense
            + $stats->spAtk
            + $stats->spDef
            + $stats->speed;

        $base = $sumaStats * $stats->nivel() * self::FACTOR_STATS / self::DIVISOR;
        $extra = $ev * (($stats->nivel() / self::DIVISOR_NIVEL_EV) / self::DIVISOR + self::BASE_EV);

        return max(0, (int) floor($base + $extra));
    }
}
