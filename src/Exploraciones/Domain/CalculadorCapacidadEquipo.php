<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

use Src\Shared\Tipos\TipoPokemon;

/**
 * RF-02/RF-03: capacidad de un equipo de expedición (dominio puro).
 *
 * capacidad = base(stats de especie normalizada 0–100) + afinidad + rol + sinergia.
 * NUNCA usa Reclutado.exp (D6): la base se deriva de pokemon_stats.base_stat.
 *
 * La capacidad del equipo es el PROMEDIO de las capacidades de sus miembros
 * (decisión de diseño documentada en active/ANALISIS_BACKEND.md): con
 * dificultades RF-06 en rango ~35–90, sumar 3 miembros dejaría sin riesgo.
 */
final class CalculadorCapacidadEquipo
{
    public const TOPE_MIN_AFINIDAD = -20;
    public const TOPE_MAX_AFINIDAD = 30;
    public const BONUS_ESPECIE_EN_POOL = 10;

    /**
     * Base 0–100: promedio de los stats base de la especie (D6).
     *
     * @param  list<int>  $statsBase
     */
    public static function baseDeStats(array $statsBase): int
    {
        if ($statsBase === []) {
            return 0;
        }

        $promedio = array_sum($statsBase) / count($statsBase);

        return max(0, min(100, (int) round($promedio)));
    }

    /**
     * Afinidad de un miembro (RF-03): +bonus si la especie está en el pool del
     * nivel; +/− alineación de tipos contra los tipos del pool vía TypeChart
     * (súper-eficaz +2, resistido −1, inmune −2 por par). Topes [−20, +30].
     *
     * @param  list<TipoPokemon>  $tiposMiembro
     * @param  list<TipoPokemon>  $tiposPool
     */
    public static function afinidadDeMiembro(array $tiposMiembro, array $tiposPool, bool $especieEnPool): int
    {
        $afinidad = $especieEnPool ? self::BONUS_ESPECIE_EN_POOL : 0;

        foreach ($tiposMiembro as $ataque) {
            foreach ($tiposPool as $defensa) {
                $efectividad = $ataque->effectivenessAgainst($defensa);

                if ($efectividad > 1.0) {
                    $afinidad += 2;
                } elseif ($efectividad === 0.0) {
                    $afinidad -= 2;
                } elseif ($efectividad < 1.0) {
                    $afinidad -= 1;
                }
            }
        }

        return max(self::TOPE_MIN_AFINIDAD, min(self::TOPE_MAX_AFINIDAD, $afinidad));
    }

    /** Capacidad de un miembro: base + afinidad + bonus de rol + bonus de sinergia. */
    public static function capacidadMiembro(int $base, int $afinidad, int $bonusRol, int $bonusSinergia): int
    {
        return max(0, $base + $afinidad + $bonusRol + $bonusSinergia);
    }

    /**
     * Capacidad del equipo: promedio (entero redondeado) de las capacidades de
     * sus miembros. Equipo vacío → 0.
     *
     * @param  list<int>  $capacidades
     */
    public static function capacidadEquipo(array $capacidades): int
    {
        if ($capacidades === []) {
            return 0;
        }

        return (int) round(array_sum($capacidades) / count($capacidades));
    }
}
