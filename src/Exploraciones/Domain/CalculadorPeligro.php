<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

/**
 * RF-01: peligro de zona de una expedición (dominio puro).
 *
 * peligroZona = (peligro(1–5) + nivel_exploración) × escala. La escala normal
 * (×5) es la dificultad estándar de los eventos; la escala alta (×10) se usa
 * para zonas de dificultad elevada.
 */
final class CalculadorPeligro
{
    public const PELIGRO_MIN = 1;
    public const PELIGRO_MAX = 5;
    public const ESCALA_NORMAL = 5;
    public const ESCALA_ALTA = 10;

    public static function peligroZona(int $peligro, int $nivelExploracion, int $escala = self::ESCALA_NORMAL): int
    {
        $peligro = max(self::PELIGRO_MIN, min(self::PELIGRO_MAX, $peligro));
        $nivel = max(1, $nivelExploracion);

        return ($peligro + $nivel) * $escala;
    }
}
