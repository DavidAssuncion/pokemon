<?php

namespace Src\Shared\Tipos;

/**
 * Matriz 18×18 de efectividades entre tipos.
 * Cada atacante → defensor devuelve 2.0 (súper eficaz), 0.5 (poco eficaz),
 * 0.0 (inmune) o 1.0 (neutral).
 * Solo se almacenan entradas distintas de 1.0; el resto se resuelve por omisión.
 */
class TypeChart
{
    private static ?array $chart = null;

    /**
     * Devuelve la matriz completa de efectividades.
     * @return array<int, array<int, float>>
     */
    public static function getChart(): array
    {
        if (self::$chart !== null) {
            return self::$chart;
        }

        self::$chart = [];
        $chart = &self::$chart;

        // ── NORMAL (1) ──
        $chart[TipoPokemon::NORMAL->value] = [
            TipoPokemon::ROCA->value     => 0.5,
            TipoPokemon::ACERO->value    => 0.5,
            TipoPokemon::FANTASMA->value => 0.0,
        ];

        // ── LUCHA (2) ──
        $chart[TipoPokemon::LUCHA->value] = [
            TipoPokemon::NORMAL->value   => 2.0,
            TipoPokemon::ROCA->value     => 2.0,
            TipoPokemon::ACERO->value    => 2.0,
            TipoPokemon::HIELO->value    => 2.0,
            TipoPokemon::SINIESTRO->value => 2.0,
            TipoPokemon::VOLADOR->value  => 0.5,
            TipoPokemon::VENENO->value   => 0.5,
            TipoPokemon::BICHO->value    => 0.5,
            TipoPokemon::PSIQUICO->value => 0.5,
            TipoPokemon::HADA->value     => 0.5,
            TipoPokemon::FANTASMA->value => 0.0,
        ];

        // ── VOLADOR (3) ──
        $chart[TipoPokemon::VOLADOR->value] = [
            TipoPokemon::LUCHA->value    => 2.0,
            TipoPokemon::BICHO->value    => 2.0,
            TipoPokemon::PLANTA->value   => 2.0,
            TipoPokemon::ROCA->value     => 0.5,
            TipoPokemon::ACERO->value    => 0.5,
            TipoPokemon::ELECTRICO->value => 0.5,
        ];

        // ── VENENO (4) ──
        $chart[TipoPokemon::VENENO->value] = [
            TipoPokemon::PLANTA->value   => 2.0,
            TipoPokemon::HADA->value     => 2.0,
            TipoPokemon::VENENO->value   => 0.5,
            TipoPokemon::TIERRA->value   => 0.5,
            TipoPokemon::ROCA->value     => 0.5,
            TipoPokemon::FANTASMA->value => 0.5,
            TipoPokemon::ACERO->value    => 0.0,
        ];

        // ── TIERRA (5) ──
        $chart[TipoPokemon::TIERRA->value] = [
            TipoPokemon::VENENO->value   => 2.0,
            TipoPokemon::ROCA->value     => 2.0,
            TipoPokemon::ACERO->value    => 2.0,
            TipoPokemon::FUEGO->value    => 2.0,
            TipoPokemon::ELECTRICO->value => 2.0,
            TipoPokemon::BICHO->value    => 0.5,
            TipoPokemon::PLANTA->value   => 0.5,
            TipoPokemon::VOLADOR->value  => 0.0,
        ];

        // ── ROCA (6) ──
        $chart[TipoPokemon::ROCA->value] = [
            TipoPokemon::VOLADOR->value  => 2.0,
            TipoPokemon::BICHO->value    => 2.0,
            TipoPokemon::FUEGO->value    => 2.0,
            TipoPokemon::HIELO->value    => 2.0,
            TipoPokemon::LUCHA->value    => 0.5,
            TipoPokemon::TIERRA->value   => 0.5,
            TipoPokemon::ACERO->value    => 0.5,
        ];

        // ── BICHO (7) ──
        $chart[TipoPokemon::BICHO->value] = [
            TipoPokemon::PLANTA->value   => 2.0,
            TipoPokemon::PSIQUICO->value => 2.0,
            TipoPokemon::SINIESTRO->value => 2.0,
            TipoPokemon::LUCHA->value    => 0.5,
            TipoPokemon::VOLADOR->value  => 0.5,
            TipoPokemon::VENENO->value   => 0.5,
            TipoPokemon::FANTASMA->value => 0.5,
            TipoPokemon::ACERO->value    => 0.5,
            TipoPokemon::FUEGO->value    => 0.5,
            TipoPokemon::HADA->value     => 0.5,
        ];

        // ── FANTASMA (8) ──
        $chart[TipoPokemon::FANTASMA->value] = [
            TipoPokemon::FANTASMA->value => 2.0,
            TipoPokemon::PSIQUICO->value => 2.0,
            TipoPokemon::SINIESTRO->value => 0.5,
            TipoPokemon::NORMAL->value   => 0.0,
        ];

        // ── ACERO (9) ──
        $chart[TipoPokemon::ACERO->value] = [
            TipoPokemon::ROCA->value     => 2.0,
            TipoPokemon::HIELO->value    => 2.0,
            TipoPokemon::HADA->value     => 2.0,
            TipoPokemon::ACERO->value    => 0.5,
            TipoPokemon::FUEGO->value    => 0.5,
            TipoPokemon::AGUA->value     => 0.5,
            TipoPokemon::ELECTRICO->value => 0.5,
        ];

        // ── FUEGO (10) ──
        $chart[TipoPokemon::FUEGO->value] = [
            TipoPokemon::PLANTA->value   => 2.0,
            TipoPokemon::HIELO->value    => 2.0,
            TipoPokemon::BICHO->value    => 2.0,
            TipoPokemon::ACERO->value    => 2.0,
            TipoPokemon::FUEGO->value    => 0.5,
            TipoPokemon::AGUA->value     => 0.5,
            TipoPokemon::ROCA->value     => 0.5,
            TipoPokemon::DRAGON->value   => 0.5,
        ];

        // ── AGUA (11) ──
        $chart[TipoPokemon::AGUA->value] = [
            TipoPokemon::TIERRA->value   => 2.0,
            TipoPokemon::ROCA->value     => 2.0,
            TipoPokemon::FUEGO->value    => 2.0,
            TipoPokemon::AGUA->value     => 0.5,
            TipoPokemon::PLANTA->value   => 0.5,
            TipoPokemon::DRAGON->value   => 0.5,
        ];

        // ── PLANTA (12) ──
        $chart[TipoPokemon::PLANTA->value] = [
            TipoPokemon::TIERRA->value   => 2.0,
            TipoPokemon::ROCA->value     => 2.0,
            TipoPokemon::AGUA->value     => 2.0,
            TipoPokemon::VOLADOR->value  => 0.5,
            TipoPokemon::VENENO->value   => 0.5,
            TipoPokemon::BICHO->value    => 0.5,
            TipoPokemon::FUEGO->value    => 0.5,
            TipoPokemon::PLANTA->value   => 0.5,
            TipoPokemon::DRAGON->value   => 0.5,
            TipoPokemon::ACERO->value    => 0.5,
        ];

        // ── ELECTRICO (13) ──
        $chart[TipoPokemon::ELECTRICO->value] = [
            TipoPokemon::VOLADOR->value  => 2.0,
            TipoPokemon::AGUA->value     => 2.0,
            TipoPokemon::PLANTA->value   => 0.5,
            TipoPokemon::ELECTRICO->value => 0.5,
            TipoPokemon::DRAGON->value   => 0.5,
            TipoPokemon::TIERRA->value   => 0.0,
        ];

        // ── PSIQUICO (14) ──
        $chart[TipoPokemon::PSIQUICO->value] = [
            TipoPokemon::LUCHA->value    => 2.0,
            TipoPokemon::VENENO->value   => 2.0,
            TipoPokemon::ACERO->value    => 0.5,
            TipoPokemon::PSIQUICO->value => 0.5,
            TipoPokemon::SINIESTRO->value => 0.0,
        ];

        // ── HIELO (15) ──
        $chart[TipoPokemon::HIELO->value] = [
            TipoPokemon::VOLADOR->value  => 2.0,
            TipoPokemon::TIERRA->value   => 2.0,
            TipoPokemon::PLANTA->value   => 2.0,
            TipoPokemon::DRAGON->value   => 2.0,
            TipoPokemon::ACERO->value    => 0.5,
            TipoPokemon::FUEGO->value    => 0.5,
            TipoPokemon::AGUA->value     => 0.5,
            TipoPokemon::HIELO->value    => 0.5,
        ];

        // ── DRAGON (16) ──
        $chart[TipoPokemon::DRAGON->value] = [
            TipoPokemon::DRAGON->value   => 2.0,
            TipoPokemon::ACERO->value    => 0.5,
            TipoPokemon::HADA->value     => 0.0,
        ];

        // ── SINIESTRO (17) ──
        $chart[TipoPokemon::SINIESTRO->value] = [
            TipoPokemon::FANTASMA->value => 2.0,
            TipoPokemon::PSIQUICO->value => 2.0,
            TipoPokemon::LUCHA->value    => 0.5,
            TipoPokemon::SINIESTRO->value => 0.5,
            TipoPokemon::HADA->value     => 0.5,
        ];

        // ── HADA (18) ──
        $chart[TipoPokemon::HADA->value] = [
            TipoPokemon::LUCHA->value    => 2.0,
            TipoPokemon::DRAGON->value   => 2.0,
            TipoPokemon::SINIESTRO->value => 2.0,
            TipoPokemon::VENENO->value   => 0.5,
            TipoPokemon::ACERO->value    => 0.5,
            TipoPokemon::FUEGO->value    => 0.5,
        ];

        return self::$chart;
    }

    /**
     * Efectividad de un tipo de ataque contra un tipo defensor.
     */
    public static function getEffectiveness(TipoPokemon $attackType, TipoPokemon $defenderType): float
    {
        return self::getChart()[$attackType->value][$defenderType->value] ?? 1.0;
    }
}
