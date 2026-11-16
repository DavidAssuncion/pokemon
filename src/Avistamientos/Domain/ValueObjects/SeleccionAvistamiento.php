<?php

declare(strict_types=1);

namespace Src\Avistamientos\Domain\ValueObjects;

/**
 * Selección determinista de la ventana: hábitat, familia y factor aplicado.
 */
final class SeleccionAvistamiento
{
    public function __construct(
        public readonly int $habitatId,
        public readonly int $evolutionChainId,
        public readonly float $factor,
    ) {
    }
}
