<?php

declare(strict_types=1);

namespace Src\Avistamientos\Domain\DataTransferObjects;

use Src\Avistamientos\Domain\ValueObjects\VentanaAvistamiento;

/**
 * Ventana vigente junto a la selección persistida que le corresponde.
 */
final class SeleccionVigente
{
    public function __construct(
        public readonly VentanaAvistamiento $ventana,
        public readonly AvistamientoRegistrado $registro,
    ) {
    }
}
