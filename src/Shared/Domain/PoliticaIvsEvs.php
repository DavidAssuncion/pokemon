<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

use Src\Pokemon\Domain\Stats\DatosStats;
use Src\Pokemon\Domain\Stats\StatsValue;

/**
 * Política pura de IV/EV por tier de dificultad.
 *
 * Escala del proyecto: IV 0-15, EV 0-255. El perfil decide cuántos stats
 * reciben el máximo (top-N por base) y qué valor lleva el resto.
 */
final class PoliticaIvsEvs
{
    public const IV_MAXIMO = 15;

    public const EV_MAXIMO = 255;

    public const IV_MITAD = 8;

    public const EV_MITAD = 128;

    private const NIVEL_MINIMO = 1;

    private const NIVEL_MAXIMO = 3;

    private const GIMNASIO_MINIMO = 1;

    private const GIMNASIO_MAXIMO = 18;

    private const GIMNASIO_SALTO_TIER = 6;

    private const TIER_MAXIMO = 3;

    /**
     * Ruta según nivel de hábitat: 1 → Nulo, 2 → TopDos, 3 → Completo.
     */
    public static function perfilRuta(int $nivel): PerfilIvsEvs
    {
        return match (self::clamp($nivel, self::NIVEL_MINIMO, self::NIVEL_MAXIMO)) {
            1 => PerfilIvsEvs::Nulo,
            2 => PerfilIvsEvs::TopDos,
            default => PerfilIvsEvs::Completo,
        };
    }

    /**
     * Entrenador según nivel de hábitat: 1 → TopUno, 2 → TopDos, 3 → Completo.
     */
    public static function perfilEntrenador(int $nivel): PerfilIvsEvs
    {
        return match (self::clamp($nivel, self::NIVEL_MINIMO, self::NIVEL_MAXIMO)) {
            1 => PerfilIvsEvs::TopUno,
            2 => PerfilIvsEvs::TopDos,
            default => PerfilIvsEvs::Completo,
        };
    }

    /**
     * Gimnasio según índice 1-18: 1-6 → TopUno, 7-12 → TopDos, 13-18 → Completo.
     * El líder sube un tier con tope en Completo.
     */
    public static function perfilGimnasio(int $indice, bool $esLider = false): PerfilIvsEvs
    {
        $indice = self::clamp($indice, self::GIMNASIO_MINIMO, self::GIMNASIO_MAXIMO);
        $tier = (int) ceil($indice / self::GIMNASIO_SALTO_TIER);

        if ($esLider) {
            $tier = min(self::TIER_MAXIMO, $tier + 1);
        }

        return match ($tier) {
            1 => PerfilIvsEvs::TopUno,
            2 => PerfilIvsEvs::TopDos,
            default => PerfilIvsEvs::Completo,
        };
    }

    public static function ivs(PerfilIvsEvs $perfil, DatosStats $base): StatsValue
    {
        return DistribuidorStatsValor::distribuirPorRanking(
            $perfil->cuantosMaximos(),
            $perfil->ivMaximo(),
            $perfil->ivResto(),
            $base,
        );
    }

    public static function evs(PerfilIvsEvs $perfil, DatosStats $base): StatsValue
    {
        return DistribuidorStatsValor::distribuirPorRanking(
            $perfil->cuantosMaximos(),
            $perfil->evMaximo(),
            $perfil->evResto(),
            $base,
        );
    }

    private static function clamp(int $valor, int $minimo, int $maximo): int
    {
        return max($minimo, min($maximo, $valor));
    }
}
