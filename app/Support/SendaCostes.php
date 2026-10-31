<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Coste de desbloqueo de un nodo de Senda según su nivel requerido.
 * El coste no se guarda en base de datos: es una regla de juego central.
 */
final class SendaCostes
{
    /** @var array<int, int> */
    public const COSTES = [
        1 => 10,
        2 => 25,
        3 => 50,
        4 => 150,
        5 => 400,
    ];

    public static function coste(int $nivelRequerido): int
    {
        $nivel = max(1, min(5, $nivelRequerido));

        return self::COSTES[$nivel];
    }
}
