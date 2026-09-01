<?php

declare(strict_types=1);

namespace Src\Shared\Tipos;

/**
 * Matriz 18×18 de efectividades entre tipos.
 * Cada atacante → defensor devuelve EFICAZ (1.5), POKO_EFICAZ (0.75),
 * INMUNE (0.25) o NEUTRAL (1.0).
 * Solo se almacenan entradas distintas de NEUTRAL; el resto se resuelve por omisión.
 */
class TypeChart
{
    public const NEUTRAL = 1.0;
    public const POKO_EFICAZ = 0.75;
    public const EFICAZ = 1.5;
    public const INMUNE = 0.25;

    private static ?array $chart = null;

    /**
     * Devuelve la matriz completa de efectividades.
     *
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
            TipoPokemon::ROCA->value => self::POKO_EFICAZ,
            TipoPokemon::ACERO->value => self::POKO_EFICAZ,
            TipoPokemon::FANTASMA->value => self::INMUNE,
        ];

        // ── LUCHA (2) ──
        $chart[TipoPokemon::LUCHA->value] = [
            TipoPokemon::NORMAL->value => self::EFICAZ,
            TipoPokemon::ROCA->value => self::EFICAZ,
            TipoPokemon::ACERO->value => self::EFICAZ,
            TipoPokemon::HIELO->value => self::EFICAZ,
            TipoPokemon::SINIESTRO->value => self::EFICAZ,
            TipoPokemon::VOLADOR->value => self::POKO_EFICAZ,
            TipoPokemon::VENENO->value => self::POKO_EFICAZ,
            TipoPokemon::BICHO->value => self::POKO_EFICAZ,
            TipoPokemon::PSIQUICO->value => self::POKO_EFICAZ,
            TipoPokemon::HADA->value => self::POKO_EFICAZ,
            TipoPokemon::FANTASMA->value => self::INMUNE,
        ];

        // ── VOLADOR (3) ──
        $chart[TipoPokemon::VOLADOR->value] = [
            TipoPokemon::LUCHA->value => self::EFICAZ,
            TipoPokemon::BICHO->value => self::EFICAZ,
            TipoPokemon::PLANTA->value => self::EFICAZ,
            TipoPokemon::ROCA->value => self::POKO_EFICAZ,
            TipoPokemon::ACERO->value => self::POKO_EFICAZ,
            TipoPokemon::ELECTRICO->value => self::POKO_EFICAZ,
        ];

        // ── VENENO (4) ──
        $chart[TipoPokemon::VENENO->value] = [
            TipoPokemon::PLANTA->value => self::EFICAZ,
            TipoPokemon::HADA->value => self::EFICAZ,
            TipoPokemon::VENENO->value => self::POKO_EFICAZ,
            TipoPokemon::TIERRA->value => self::POKO_EFICAZ,
            TipoPokemon::ROCA->value => self::POKO_EFICAZ,
            TipoPokemon::FANTASMA->value => self::POKO_EFICAZ,
            TipoPokemon::ACERO->value => self::INMUNE,
        ];

        // ── TIERRA (5) ──
        $chart[TipoPokemon::TIERRA->value] = [
            TipoPokemon::VENENO->value => self::EFICAZ,
            TipoPokemon::ROCA->value => self::EFICAZ,
            TipoPokemon::ACERO->value => self::EFICAZ,
            TipoPokemon::FUEGO->value => self::EFICAZ,
            TipoPokemon::ELECTRICO->value => self::EFICAZ,
            TipoPokemon::BICHO->value => self::POKO_EFICAZ,
            TipoPokemon::PLANTA->value => self::POKO_EFICAZ,
            TipoPokemon::VOLADOR->value => self::INMUNE,
        ];

        // ── ROCA (6) ──
        $chart[TipoPokemon::ROCA->value] = [
            TipoPokemon::VOLADOR->value => self::EFICAZ,
            TipoPokemon::BICHO->value => self::EFICAZ,
            TipoPokemon::FUEGO->value => self::EFICAZ,
            TipoPokemon::HIELO->value => self::EFICAZ,
            TipoPokemon::LUCHA->value => self::POKO_EFICAZ,
            TipoPokemon::TIERRA->value => self::POKO_EFICAZ,
            TipoPokemon::ACERO->value => self::POKO_EFICAZ,
        ];

        // ── BICHO (7) ──
        $chart[TipoPokemon::BICHO->value] = [
            TipoPokemon::PLANTA->value => self::EFICAZ,
            TipoPokemon::PSIQUICO->value => self::EFICAZ,
            TipoPokemon::SINIESTRO->value => self::EFICAZ,
            TipoPokemon::LUCHA->value => self::POKO_EFICAZ,
            TipoPokemon::VOLADOR->value => self::POKO_EFICAZ,
            TipoPokemon::VENENO->value => self::POKO_EFICAZ,
            TipoPokemon::FANTASMA->value => self::POKO_EFICAZ,
            TipoPokemon::ACERO->value => self::POKO_EFICAZ,
            TipoPokemon::FUEGO->value => self::POKO_EFICAZ,
            TipoPokemon::HADA->value => self::POKO_EFICAZ,
        ];

        // ── FANTASMA (8) ──
        $chart[TipoPokemon::FANTASMA->value] = [
            TipoPokemon::FANTASMA->value => self::EFICAZ,
            TipoPokemon::PSIQUICO->value => self::EFICAZ,
            TipoPokemon::SINIESTRO->value => self::POKO_EFICAZ,
            TipoPokemon::NORMAL->value => self::INMUNE,
        ];

        // ── ACERO (9) ──
        $chart[TipoPokemon::ACERO->value] = [
            TipoPokemon::ROCA->value => self::EFICAZ,
            TipoPokemon::HIELO->value => self::EFICAZ,
            TipoPokemon::HADA->value => self::EFICAZ,
            TipoPokemon::ACERO->value => self::POKO_EFICAZ,
            TipoPokemon::FUEGO->value => self::POKO_EFICAZ,
            TipoPokemon::AGUA->value => self::POKO_EFICAZ,
            TipoPokemon::ELECTRICO->value => self::POKO_EFICAZ,
        ];

        // ── FUEGO (10) ──
        $chart[TipoPokemon::FUEGO->value] = [
            TipoPokemon::PLANTA->value => self::EFICAZ,
            TipoPokemon::HIELO->value => self::EFICAZ,
            TipoPokemon::BICHO->value => self::EFICAZ,
            TipoPokemon::ACERO->value => self::EFICAZ,
            TipoPokemon::FUEGO->value => self::POKO_EFICAZ,
            TipoPokemon::AGUA->value => self::POKO_EFICAZ,
            TipoPokemon::ROCA->value => self::POKO_EFICAZ,
            TipoPokemon::DRAGON->value => self::POKO_EFICAZ,
        ];

        // ── AGUA (11) ──
        $chart[TipoPokemon::AGUA->value] = [
            TipoPokemon::TIERRA->value => self::EFICAZ,
            TipoPokemon::ROCA->value => self::EFICAZ,
            TipoPokemon::FUEGO->value => self::EFICAZ,
            TipoPokemon::AGUA->value => self::POKO_EFICAZ,
            TipoPokemon::PLANTA->value => self::POKO_EFICAZ,
            TipoPokemon::DRAGON->value => self::POKO_EFICAZ,
        ];

        // ── PLANTA (12) ──
        $chart[TipoPokemon::PLANTA->value] = [
            TipoPokemon::TIERRA->value => self::EFICAZ,
            TipoPokemon::ROCA->value => self::EFICAZ,
            TipoPokemon::AGUA->value => self::EFICAZ,
            TipoPokemon::VOLADOR->value => self::POKO_EFICAZ,
            TipoPokemon::VENENO->value => self::POKO_EFICAZ,
            TipoPokemon::BICHO->value => self::POKO_EFICAZ,
            TipoPokemon::FUEGO->value => self::POKO_EFICAZ,
            TipoPokemon::PLANTA->value => self::POKO_EFICAZ,
            TipoPokemon::DRAGON->value => self::POKO_EFICAZ,
            TipoPokemon::ACERO->value => self::POKO_EFICAZ,
        ];

        // ── ELECTRICO (13) ──
        $chart[TipoPokemon::ELECTRICO->value] = [
            TipoPokemon::VOLADOR->value => self::EFICAZ,
            TipoPokemon::AGUA->value => self::EFICAZ,
            TipoPokemon::PLANTA->value => self::POKO_EFICAZ,
            TipoPokemon::ELECTRICO->value => self::POKO_EFICAZ,
            TipoPokemon::DRAGON->value => self::POKO_EFICAZ,
            TipoPokemon::TIERRA->value => self::INMUNE,
        ];

        // ── PSIQUICO (14) ──
        $chart[TipoPokemon::PSIQUICO->value] = [
            TipoPokemon::LUCHA->value => self::EFICAZ,
            TipoPokemon::VENENO->value => self::EFICAZ,
            TipoPokemon::ACERO->value => self::POKO_EFICAZ,
            TipoPokemon::PSIQUICO->value => self::POKO_EFICAZ,
            TipoPokemon::SINIESTRO->value => self::INMUNE,
        ];

        // ── HIELO (15) ──
        $chart[TipoPokemon::HIELO->value] = [
            TipoPokemon::VOLADOR->value => self::EFICAZ,
            TipoPokemon::TIERRA->value => self::EFICAZ,
            TipoPokemon::PLANTA->value => self::EFICAZ,
            TipoPokemon::DRAGON->value => self::EFICAZ,
            TipoPokemon::ACERO->value => self::POKO_EFICAZ,
            TipoPokemon::FUEGO->value => self::POKO_EFICAZ,
            TipoPokemon::AGUA->value => self::POKO_EFICAZ,
            TipoPokemon::HIELO->value => self::POKO_EFICAZ,
        ];

        // ── DRAGON (16) ──
        $chart[TipoPokemon::DRAGON->value] = [
            TipoPokemon::DRAGON->value => self::EFICAZ,
            TipoPokemon::ACERO->value => self::POKO_EFICAZ,
            TipoPokemon::HADA->value => self::INMUNE,
        ];

        // ── SINIESTRO (17) ──
        $chart[TipoPokemon::SINIESTRO->value] = [
            TipoPokemon::FANTASMA->value => self::EFICAZ,
            TipoPokemon::PSIQUICO->value => self::EFICAZ,
            TipoPokemon::LUCHA->value => self::POKO_EFICAZ,
            TipoPokemon::SINIESTRO->value => self::POKO_EFICAZ,
            TipoPokemon::HADA->value => self::POKO_EFICAZ,
        ];

        // ── HADA (18) ──
        $chart[TipoPokemon::HADA->value] = [
            TipoPokemon::LUCHA->value => self::EFICAZ,
            TipoPokemon::DRAGON->value => self::EFICAZ,
            TipoPokemon::SINIESTRO->value => self::EFICAZ,
            TipoPokemon::VENENO->value => self::POKO_EFICAZ,
            TipoPokemon::ACERO->value => self::POKO_EFICAZ,
            TipoPokemon::FUEGO->value => self::POKO_EFICAZ,
        ];

        return self::$chart;
    }

    /**
     * Efectividad de un tipo de ataque contra un tipo defensor.
     */
    public static function getEffectiveness(TipoPokemon $attackType, TipoPokemon $defenderType): float
    {
        return self::getChart()[$attackType->value][$defenderType->value] ?? self::NEUTRAL;
    }
}
