<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Coste de desbloqueo de un nodo de Senda según su tramo.
 *
 * El tramo es el `required_level` original 1-5. Los nodos de tramo 4-5 se
 * muestran en el nivel visible 3 y conservan su coste de maestría. El coste no
 * se guarda en base de datos: es una regla de juego central, ajustable desde
 * `config/sendas.php`.
 */
final class SendaCostes
{
    /** @var array<int, int> */
    public const COSTES = [
        1 => 2,
        2 => 5,
        3 => 10,
        4 => 25,
        5 => 60,
    ];

    /**
     * Costes por tramo, con los valores por defecto completados desde config.
     *
     * @return array<int, int>
     */
    public static function costes(): array
    {
        $costes = self::COSTES;
        $config = config('sendas.costes');

        if (! is_array($config)) {
            return $costes;
        }

        foreach ($costes as $tramo => $defecto) {
            if (isset($config[$tramo]) && is_numeric($config[$tramo])) {
                $costes[$tramo] = (int) $config[$tramo];
            }
        }

        return $costes;
    }

    public static function coste(int $tramo): int
    {
        $tramo = max(1, min(5, $tramo));

        return self::costes()[$tramo];
    }
}
