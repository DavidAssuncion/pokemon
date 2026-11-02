<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

use Src\Pokemon\Domain\Stats\DatosStats;
use Src\Pokemon\Domain\Stats\StatsValue;

/**
 * Algoritmo compartido de distribución de EVs/IVs por ranking de stats base.
 *
 * Los N stats base más altos reciben $valorPrincipal y los restantes
 * $valorResto. Los empates se resuelven con el orden fijo
 * (hp, atk, def, spAtk, spDef, speed).
 *
 * Reutilizado por EvsRangoEntrenador, IvsRangoEntrenador y PoliticaIvsEvs.
 */
final class DistribuidorStatsValor
{
    /** @var list<string> */
    private const ORDEN = ['hp', 'atk', 'def', 'spAtk', 'spDef', 'speed'];

    private const TOTAL_STATS = 6;

    /**
     * Mantiene el reparto histórico top-2 (2 mejores stats base → principal).
     */
    public static function distribuir(int $valorPrincipal, int $valorResto, DatosStats $statsBase): StatsValue
    {
        return self::distribuirPorRanking(2, $valorPrincipal, $valorResto, $statsBase);
    }

    /**
     * Reparte los valores entre los $cuantosPrincipales mejores stats base.
     *
     * @param  int  $cuantosPrincipales  0..6 (se aplica clamp): 0 → todos resto, 6 → todos principal
     */
    public static function distribuirPorRanking(
        int $cuantosPrincipales,
        int $valorPrincipal,
        int $valorResto,
        DatosStats $statsBase,
    ): StatsValue {
        $cuantos = max(0, min(self::TOTAL_STATS, $cuantosPrincipales));
        $posicion = array_flip(self::ORDEN);

        /** @var list<array{0: string, 1: int}> $pares */
        $pares = [
            ['hp', $statsBase->hp],
            ['atk', $statsBase->atk],
            ['def', $statsBase->def],
            ['spAtk', $statsBase->spAtk],
            ['spDef', $statsBase->spDef],
            ['speed', $statsBase->speed],
        ];

        usort($pares, static function (array $a, array $b) use ($posicion): int {
            $cmp = $b[1] <=> $a[1];

            return $cmp !== 0 ? $cmp : ($posicion[$a[0]] <=> $posicion[$b[0]]);
        });

        $valores = array_fill_keys(self::ORDEN, (float) $valorResto);

        for ($i = 0; $i < $cuantos; $i++) {
            $valores[$pares[$i][0]] = (float) $valorPrincipal;
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
