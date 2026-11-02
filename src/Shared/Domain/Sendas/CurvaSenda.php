<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Sendas;

/**
 * Curva de experiencia de las Sendas de tipo.
 *
 * Umbrales: 0 / 9.000 / 18.000 / 36.000 / 72.000. El nivel 5 es el máximo y
 * coincide con el tope de experiencia presentable.
 */
final class CurvaSenda
{
    public const NIVEL_MAXIMO = 5;

    public const EXP_MAXIMA = 72_000;

    public const EXP_POR_CARAMELO = 100;

    /**
     * Umbral de entrada de cada nivel, del 1 al 5.
     *
     * @var array<int, int>
     */
    public const UMBRALES = [
        1 => 0,
        2 => 9_000,
        3 => 18_000,
        4 => 36_000,
        5 => 72_000,
    ];

    /**
     * Umbral de experiencia para alcanzar el nivel dado.
     *
     * Por encima del nivel máximo satura en {@see self::EXP_MAXIMA}, de modo
     * que `umbral(6)` representa el tope y no un sexto nivel.
     */
    public static function umbral(int $nivel): int
    {
        if ($nivel > self::NIVEL_MAXIMO) {
            return self::EXP_MAXIMA;
        }

        return self::UMBRALES[max(1, $nivel)];
    }

    /**
     * Nivel alcanzado con la experiencia dada.
     *
     * Es el mayor nivel cuyo umbral es menor o igual que la experiencia,
     * acotado a {@see self::NIVEL_MAXIMO}. La experiencia negativa se trata
     * como 0 y la que supera el tope mantiene el nivel 5.
     */
    public static function nivel(int $exp): int
    {
        $exp = max(0, $exp);
        $nivel = 1;
        while ($nivel < self::NIVEL_MAXIMO && self::umbral($nivel + 1) <= $exp) {
            $nivel++;
        }

        return $nivel;
    }
}
