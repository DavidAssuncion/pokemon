<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Curva de progreso de las Sendas de tipo.
 *
 * La experiencia se acumula en la cuenta. El nivel de la Senda se deriva de
 * la experiencia, pero nunca supera el cap que permite el nivel de cuenta.
 */
final class SendaProgreso
{
    public const NIVEL_MAXIMO = 5;

    /** @var array<int, int> */
    public const UMBRALES = [
        1 => 0,
        2 => 500,
        3 => 2_000,
        4 => 6_000,
        5 => 15_000,
    ];

    public const NIVELES_POR_TRAMO_CUENTA = 20;

    public static function nivel(int $exp): int
    {
        $exp = max(0, $exp);

        $nivel = 1;
        foreach (self::UMBRALES as $umbralNivel => $umbral) {
            if ($exp >= $umbral) {
                $nivel = $umbralNivel;
            }
        }

        return min(self::NIVEL_MAXIMO, $nivel);
    }

    public static function capPorNivelCuenta(int $nivelCuenta): int
    {
        $nivelCuenta = max(1, $nivelCuenta);

        return min(
            self::NIVEL_MAXIMO,
            intdiv($nivelCuenta, self::NIVELES_POR_TRAMO_CUENTA) + 1,
        );
    }

    /**
     * @return array{
     *     exp: int,
     *     nivel: int,
     *     nivel_por_exp: int,
     *     cap: int,
     *     nivel_maximo: int,
     *     exp_inicio: int,
     *     exp_siguiente: int|null,
     *     exp_faltante: int,
     *     porcentaje: int,
     *     progreso_vertical: int,
     *     maximo: bool,
     *     bloqueado_por_cuenta: bool
     * }
     */
    public static function resumen(int $exp, int $nivelCuenta): array
    {
        $exp = max(0, $exp);
        $nivelPorExp = self::nivel($exp);
        $cap = self::capPorNivelCuenta($nivelCuenta);
        $nivel = min($nivelPorExp, $cap);

        $expInicio = self::UMBRALES[$nivel];
        $expSiguiente = self::UMBRALES[$nivel + 1] ?? null;

        if ($expSiguiente === null) {
            $porcentaje = 100;
        } else {
            $rango = $expSiguiente - $expInicio;
            $porcentaje = $rango > 0
                ? (int) floor((($exp - $expInicio) / $rango) * 100)
                : 100;
        }

        $porcentaje = max(0, min(100, $porcentaje));
        $progresoVertical = (int) round(
            ((($nivel - 1) + ($porcentaje / 100)) / self::NIVEL_MAXIMO) * 100,
        );

        return [
            'exp' => $exp,
            'nivel' => $nivel,
            'nivel_por_exp' => $nivelPorExp,
            'cap' => $cap,
            'nivel_maximo' => self::NIVEL_MAXIMO,
            'exp_inicio' => $expInicio,
            'exp_siguiente' => $expSiguiente,
            'exp_faltante' => $expSiguiente === null ? 0 : max(0, $expSiguiente - $exp),
            'porcentaje' => $porcentaje,
            'progreso_vertical' => max(0, min(100, $progresoVertical)),
            'maximo' => $nivel >= self::NIVEL_MAXIMO,
            'bloqueado_por_cuenta' => $nivelPorExp > $cap,
        ];
    }
}
