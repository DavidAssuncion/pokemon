<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Ports;

/**
 * Puerto de azar del dominio.
 *
 * Aísla las funciones aleatorias nativas para permitir dobles deterministas
 * en pruebas y mantener el dominio libre de detalles de infraestructura.
 */
interface GeneradorAleatorio
{
    /**
     * Entero pseudoaleatorio en el rango inclusivo [$min, $max].
     */
    public function entero(int $min, int $max): int;

    /**
     * Flotante pseudoaleatorio en el rango [0, 1).
     */
    public function flotante(): float;
}
