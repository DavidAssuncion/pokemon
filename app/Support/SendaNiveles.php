<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Move;

/**
 * Asigna el nivel 1-5 de una Senda a cada movimiento aprendible.
 *
 * La potencia manda en los movimientos dañinos y el nivel global del catálogo
 * se usa para los movimientos de estado o sin potencia. Los ajustes manuales
 * conservan los ejemplos de diseño (Lanzallamas 3, Llamarada 5).
 */
final class SendaNiveles
{
    /** @var array<string, int> */
    private const AJUSTES_MANUALES = [
        'ember' => 1,
        'flamethrower' => 3,
        'fire-blast' => 5,
        'will-o-wisp' => 2,
    ];

    public static function para(Move $move): int
    {
        $slug = (string) $move->slug;
        if (isset(self::AJUSTES_MANUALES[$slug])) {
            return self::AJUSTES_MANUALES[$slug];
        }

        $categoria = self::categoria($move);
        $potencia = max(0, (int) $move->base_power);

        if ($categoria === 'estado' || $potencia === 0) {
            return self::porNivelRequerido((int) $move->nivel_requerido);
        }

        return self::porPotencia($potencia);
    }

    private static function porPotencia(int $potencia): int
    {
        return match (true) {
            $potencia <= 40 => 1,
            $potencia <= 60 => 2,
            $potencia <= 80 => 3,
            $potencia <= 100 => 4,
            default => 5,
        };
    }

    private static function porNivelRequerido(int $nivelRequerido): int
    {
        return match (true) {
            $nivelRequerido <= 20 => 1,
            $nivelRequerido <= 35 => 2,
            $nivelRequerido <= 50 => 3,
            $nivelRequerido <= 70 => 4,
            default => 5,
        };
    }

    private static function categoria(Move $move): string
    {
        return in_array($move->category, ['fisico', 'especial', 'estado'], true)
            ? (string) $move->category
            : 'estado';
    }
}
