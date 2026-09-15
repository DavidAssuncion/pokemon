<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

use Src\Pokemon\Domain\Stats\DatosStats;
use Src\Pokemon\Domain\Stats\StatsValue;

/**
 * Algoritmo compartido de distribución de EVs/IVs: los 2 mejores stats base
 * reciben $valorPrincipal y los 4 restantes $valorResto. Los empates se
 * resuelven con el orden fijo (hp, atk, def, spAtk, spDef, speed).
 *
 * Reutilizado por EvsRangoEntrenador e IvsRangoEntrenador.
 */
final class DistribuidorStatsValor
{
    public static function distribuir(int $valorPrincipal, int $valorResto, DatosStats $statsBase): StatsValue
    {
        $orden = ['hp', 'atk', 'def', 'spAtk', 'spDef', 'speed'];

        $pares = [
            ['hp', $statsBase->hp],
            ['atk', $statsBase->atk],
            ['def', $statsBase->def],
            ['spAtk', $statsBase->spAtk],
            ['spDef', $statsBase->spDef],
            ['speed', $statsBase->speed],
        ];

        usort($pares, function (array $a, array $b) use ($orden): int {
            $cmp = $b[1] <=> $a[1];

            return $cmp !== 0 ? $cmp : (array_search($a[0], $orden, true) <=> array_search($b[0], $orden, true));
        });

        $valores = array_fill_keys($orden, (float) $valorResto);

        foreach ([$pares[0][0], $pares[1][0]] as $stat) {
            $valores[$stat] = (float) $valorPrincipal;
        }

        return new StatsValue(
            hp: $valores['hp'],
            attack: $valores['atk'],
            defense: $valores['def'],
            spAtk: $valores['spAtk'],
            spDef: $valores['spDef'],
            speed: $valores['speed'],
        );
    }
}
