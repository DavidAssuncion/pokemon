<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Random;

use Src\Shared\Domain\Ports\GeneradorAleatorio;

/**
 * Adaptador de azar basado en las funciones nativas de PHP.
 *
 * Replica `mt_rand()` para conservar la secuencia pseudoaleatoria actual
 * de Battle y no alterar el comportamiento existente.
 */
final class GeneradorAleatorioPhp implements GeneradorAleatorio
{
    public function entero(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }

    public function flotante(): float
    {
        return mt_rand() / mt_getrandmax();
    }
}
