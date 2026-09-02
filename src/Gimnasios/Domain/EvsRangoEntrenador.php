<?php

declare(strict_types=1);

namespace Src\Gimnasios\Domain;

use Src\Pokemon\Domain\Stats\StatsValue;

final class EvsRangoEntrenador
{
    public const ALTO_MANDO_PRINCIPAL = 252;
    public const ALTO_MANDO_RESTO = 128;
    public const LIDER_PRINCIPAL = 128;
    public const LIDER_RESTO = 64;
    public const GIMNASIO_PRINCIPAL = 64;
    public const GIMNASIO_RESTO = 64;
    public const RUTA_PRINCIPAL = 0;
    public const RUTA_RESTO = 0;

    /**
     * Reparte EVs entre los 6 stats: los 2 mejores stats base reciben
     * $evPrincipal y los 4 restantes $evResto. Los empates se resuelven
     * con el orden fijo (hp, atk, def, spAtk, spDef, speed).
     *
     * @param  array{hp: int, atk: int, def: int, spAtk: int, spDef: int, speed: int}  $statsBase
     */
    public static function distribuir(int $evPrincipal, int $evResto, array $statsBase): StatsValue
    {
        $orden = ['hp', 'atk', 'def', 'spAtk', 'spDef', 'speed'];

        $pares = [];
        foreach ($orden as $stat) {
            $pares[] = [$stat, $statsBase[$stat]];
        }

        usort($pares, function (array $a, array $b) use ($orden): int {
            $cmp = $b[1] <=> $a[1];

            return $cmp !== 0 ? $cmp : (array_search($a[0], $orden, true) <=> array_search($b[0], $orden, true));
        });

        $valores = array_fill_keys($orden, (float) $evResto);

        foreach ([$pares[0][0], $pares[1][0]] as $stat) {
            $valores[$stat] = (float) $evPrincipal;
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
