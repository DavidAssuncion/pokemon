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
 * - Para el jugador EV = 0 (Reclutado no tiene EVs).
 * - Para rivales de gimnasio/mazmorra se usa el EV del rival (EvsRangoEntrenador):
 *   la suma de los EVs distribuidos entre los 6 stats.
 */
final class CalculadorCP
{
    public static function calcular(BattleStats $stats, int $ev): int
    {
        $sumaStats = $stats->hp + $stats->attack + $stats->defense + $stats->spAtk + $stats->spDef + $stats->speed;
        $base = $sumaStats * $stats->nivel() * 6 / 100;
        $extra = $ev * (($stats->nivel() / 4) / 100 + 2);

        return (int) floor($base + $extra);
    }
}
