<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Random;

use Src\Shared\Domain\Ports\GeneradorAleatorio;

/**
 * Implementación predeterminada del puerto de azar.
 *
 * Usa las funciones nativas de PHP (`mt_rand`) para conservar el
 * comportamiento existente cuando no se inyecta un doble determinista.
 */
final class GeneradorAleatorioPredeterminado implements GeneradorAleatorio
{
    /**
     * Entero pseudoaleatorio en el rango inclusivo [$min, $max].
     */
    public function entero(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }

    /**
     * Flotante pseudoaleatorio en el rango [0, 1).
     */
    public function flotante(): float
    {
        return mt_rand() / mt_getrandmax();
    }
}
