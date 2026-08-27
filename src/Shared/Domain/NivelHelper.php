<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

final class NivelHelper
{
    /**
     * Curva media ×10: exp_total = 10 × nivel³ → nivel 100 = 10.000.000 exp.
     * Sin tope de nivel: devuelve el nivel que corresponda, incluso > 100.
     *
     * La raíz cúbica flotante se corrige con potencias exactas para evitar
     * errores de precisión (125 ** (1/3) === 4.999... → nivel 5).
     */
    public static function nivelDesdeExperiencia(int $experiencia): int
    {
        if ($experiencia <= 0) {
            return 1;
        }

        $base = $experiencia / 10;
        $nivel = (int) floor($base ** (1 / 3));

        while (($nivel + 1) ** 3 <= $base) {
            $nivel++;
        }

        while ($nivel ** 3 > $base) {
            $nivel--;
        }

        return max(1, $nivel);
    }

    /**
     * EXP al derrotar un pokémon salvaje (fórmula Gen V+): floor((base × nivel) / 5).
     */
    public static function expDerrota(int $baseExperience, int $nivelSalvaje): int
    {
        return intdiv($baseExperience * $nivelSalvaje, 5);
    }

    /**
     * Experiencia requerida para alcanzar un nivel (curva media ×10: 10 × nivel³).
     */
    public static function experienciaParaNivel(int $nivel): int
    {
        return 10 * $nivel ** 3;
    }

    /**
     * Progreso 0-100 hacia el siguiente nivel, clampado a [0, 100].
     */
    public static function progresoHaciaSiguienteNivel(int $experiencia): int
    {
        $nivel = self::nivelDesdeExperiencia($experiencia);
        $inicio = self::experienciaParaNivel($nivel);
        $fin = self::experienciaParaNivel($nivel + 1);
        $rango = $fin - $inicio;
        if ($rango <= 0) {
            return 100;
        }

        $progreso = (int) round((($experiencia - $inicio) / $rango) * 100);

        return max(0, min(100, $progreso));
    }
}
