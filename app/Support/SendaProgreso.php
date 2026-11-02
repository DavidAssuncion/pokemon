<?php

declare(strict_types=1);

namespace App\Support;

use Src\Shared\Domain\Sendas\CurvaSenda;

/**
 * Adaptador de la curva de progreso de las Sendas de tipo.
 *
 * La regla pura vive en {@see CurvaSenda}. Este adaptador permite ajustar los
 * umbrales y la experiencia desde `config/sendas.php` manteniendo la API
 * pública histórica.
 *
 * La Senda tiene 5 niveles con umbrales 0 / 9.000 / 18.000 / 36.000 / 72.000.
 * El nivel 5 coincide con {@see self::EXP_MAXIMA}, el tope de presentación.
 */
final class SendaProgreso
{
    /**
     * Nivel máximo alcanzable de una Senda.
     */
    public const NIVEL_MAXIMO = CurvaSenda::NIVEL_MAXIMO;

    /**
     * Experiencia máxima de presentación: tope del nivel 5.
     */
    public const EXP_MAXIMA = CurvaSenda::EXP_MAXIMA;

    /**
     * Experiencia que otorga cada caramelo de tipo consumido.
     */
    public const EXP_POR_CARAMELO = CurvaSenda::EXP_POR_CARAMELO;

    /**
     * Umbral de entrada de cada nivel, del 1 al 5.
     *
     * @return array<int, int>
     */
    public static function umbrales(): array
    {
        $umbrales = CurvaSenda::UMBRALES;
        $config = config('sendas.umbrales');

        if (! is_array($config)) {
            return $umbrales;
        }

        foreach ($umbrales as $nivel => $defecto) {
            if (isset($config[$nivel]) && is_numeric($config[$nivel])) {
                $umbrales[$nivel] = (int) $config[$nivel];
            }
        }

        return $umbrales;
    }

    /**
     * Umbral de experiencia para alcanzar el nivel dado.
     *
     * Los niveles 1-5 salen de la tabla configurada (el nivel se clampa a >= 1).
     * Por encima de {@see self::NIVEL_MAXIMO} satura en {@see self::expMaxima()},
     * de modo que `umbral(6)` se usa como `exp_siguiente` del nivel 5 y
     * representa el tope, no un sexto nivel.
     */
    public static function umbral(int $nivel): int
    {
        if ($nivel > self::NIVEL_MAXIMO) {
            return self::expMaxima();
        }

        return self::umbrales()[max(1, $nivel)];
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

    /**
     * Resumen de progreso listo para presentación.
     *
     * `exp` viaja clampeada a `[0, exp_maxima]`; la experiencia real acumulada
     * por encima del tope se conserva en `player_type_paths.exp` y no se expone
     * aquí. En el nivel máximo `exp_siguiente` es el tope, `exp_faltante` es 0
     * y el porcentaje es 100.
     *
     * @return array{
     *     exp: int,
     *     exp_maxima: int,
     *     nivel: int,
     *     nivel_maximo: int,
     *     exp_siguiente: int,
     *     exp_faltante: int,
     *     porcentaje: int,
     *     exp_por_caramelo: int
     * }
     */
    public static function resumen(int $exp): array
    {
        $expMaxima = self::expMaxima();
        $expVista = min(max(0, $exp), $expMaxima);
        $nivel = self::nivel($expVista);
        $inicio = self::umbral($nivel);
        $siguiente = $nivel >= self::NIVEL_MAXIMO ? $expMaxima : self::umbral($nivel + 1);
        $rango = $siguiente - $inicio;
        $porcentaje = $rango > 0
            ? intdiv(($expVista - $inicio) * 100, $rango)
            : ($nivel >= self::NIVEL_MAXIMO ? 100 : 0);

        return [
            'exp' => $expVista,
            'exp_maxima' => $expMaxima,
            'nivel' => $nivel,
            'nivel_maximo' => self::NIVEL_MAXIMO,
            'exp_siguiente' => $siguiente,
            'exp_faltante' => max(0, $siguiente - $expVista),
            'porcentaje' => max(0, min(100, $porcentaje)),
            'exp_por_caramelo' => self::expPorCaramelo(),
        ];
    }

    /**
     * Tope de experiencia configurado.
     */
    public static function expMaxima(): int
    {
        return self::enteroConfigurado('sendas.experiencia.maxima', self::EXP_MAXIMA);
    }

    /**
     * Experiencia por caramelo configurada.
     */
    public static function expPorCaramelo(): int
    {
        return self::enteroConfigurado('sendas.experiencia.por_caramelo', self::EXP_POR_CARAMELO);
    }

    private static function enteroConfigurado(string $clave, int $defecto): int
    {
        $valor = config($clave);

        return is_numeric($valor) ? (int) $valor : $defecto;
    }
}
