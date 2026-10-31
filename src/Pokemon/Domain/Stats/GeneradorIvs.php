<?php

declare(strict_types=1);

namespace Src\Pokemon\Domain\Stats;

/**
 * Generador de IVs (Individual Values) para reclutados.
 * IVs van de 0 a 15; se almacenan como float en StatsValue.
 */
final class GeneradorIvs
{
    public const NIVEL_NIDO_MAX = 5;

    /**
     * IVs aleatorios 0-15 por stat (encuentro salvaje no-shiny / fallback).
     * aleatorio: callable(): float returning [0, 1).
     */
    public static function aleatorios(?callable $aleatorio = null): StatsValue
    {
        return self::generar(0, 15, $aleatorio);
    }

    /**
     * IVs máximos (15/15/15/15/15/15): captura shiny salvaje + huevos shiny.
     */
    public static function maximos(): StatsValue
    {
        return new StatsValue(hp: 15.0, attack: 15.0, defense: 15.0, spAtk: 15.0, spDef: 15.0, speed: 15.0);
    }

    /**
     * IVs de huevo: rango 0-4 sin nido y sube con el nivel de nido hasta 15.
     * aleatorio: callable(): float returning [0, 1).
     */
    public static function huevo(int $nivelNido = 0, ?callable $aleatorio = null): StatsValue
    {
        $rango = self::rangoHuevo($nivelNido);

        return self::generar($rango->min, $rango->max, $aleatorio);
    }

    /**
     * IVs de reclutamiento: sin nido 0-15; con nido se garantiza el mínimo
     * del rango del nido, pero el máximo sigue siendo 15.
     * Ej.: nivel 1 → 4-15; nivel 5 → 15.
     * aleatorio: callable(): float returning [0, 1).
     */
    public static function reclutamiento(int $nivelNido = 0, ?callable $aleatorio = null): StatsValue
    {
        $minimo = self::rangoHuevo($nivelNido)->min;

        return self::generar($minimo, 15, $aleatorio);
    }

    /**
     * Rango de IVs por stat para un nivel de nido (0..5).
     */
    public static function rangoHuevo(int $nivelNido): RangoIvs
    {
        return match (max(0, min(self::NIVEL_NIDO_MAX, $nivelNido))) {
            0 => new RangoIvs(0, 3),
            1 => new RangoIvs(4, 7),
            2 => new RangoIvs(8, 9),
            3 => new RangoIvs(10, 11),
            4 => new RangoIvs(12, 14),
            default => new RangoIvs(15, 15),
        };
    }

    /**
     * Genera 6 IVs dentro del rango [min, max]. Fuente aleatoria opcional
     * inyectable (tests); por defecto usa random_int determinista.
     *
     * @param  callable(): float|null  $aleatorio  devuelve [0, 1)
     */
    private static function generar(int $min, int $max, ?callable $aleatorio): StatsValue
    {
        if ($aleatorio !== null) {
            $iv = static fn (): float => min((float) $max, (float) (int) ($min + $aleatorio() * ($max - $min + 1)));
        } else {
            $iv = static fn (): float => (float) random_int($min, $max);
        }

        return new StatsValue(
            hp: $iv(),
            attack: $iv(),
            defense: $iv(),
            spAtk: $iv(),
            spDef: $iv(),
            speed: $iv(),
        );
    }
}
