<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\Domain\Collections;

use Src\CombateEntrenadores\Domain\DataTransferObjects\MovimientoSintetico;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de movimientos sintéticos generados por tipo.
 */
final class MovimientosSinteticosCollection extends Collection
{
    public string $type = MovimientoSintetico::class;
}
