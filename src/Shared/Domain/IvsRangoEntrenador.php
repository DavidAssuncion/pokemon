<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

use Src\Pokemon\Domain\Stats\DatosStats;
use Src\Pokemon\Domain\Stats\StatsValue;

/**
 * IVs por dificultad de rival. Entrenadores hábitat usan rangoPorNivelEntrenador;
 * gimnasios y líderes usan las constantes.
 */
final class IvsRangoEntrenador
{
    public const GIMNASIO_PRINCIPAL = 12;
    public const GIMNASIO_RESTO = 10;
    public const LIDER_PRINCIPAL = 14;
    public const LIDER_RESTO = 12;

    /**
     * Rango [ivPrincipal, ivResto] para entrenadores de hábitat según nivel (1-3).
     *
     * @return array{0: int, 1: int}
     */
    public static function rangoPorNivelEntrenador(int $nivel): array
    {
        return match (max(1, min(3, $nivel))) {
            1 => [8, 6],
            2 => [10, 8],
            3 => [12, 10],
            default => [8, 6],
        };
    }

    /**
     * Distribuye IVs entre los 6 stats usando el algoritmo compartido
     * (mismo comportamiento que EvsRangoEntrenador::distribuir).
     */
    public static function distribuir(int $ivPrincipal, int $ivResto, DatosStats $statsBase): StatsValue
    {
        return DistribuidorStatsValor::distribuir($ivPrincipal, $ivResto, $statsBase);
    }
}
