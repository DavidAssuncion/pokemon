<?php

declare(strict_types=1);

namespace Src\Gimnasios\Domain;

use Src\Pokemon\Domain\Stats\DatosStats;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Shared\Domain\DistribuidorStatsValor;

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
     * Algoritmo compartido con IvsRangoEntrenador (DistribuidorStatsValor).
     */
    public static function distribuir(int $evPrincipal, int $evResto, DatosStats $statsBase): StatsValue
    {
        return DistribuidorStatsValor::distribuir($evPrincipal, $evResto, $statsBase);
    }
}
