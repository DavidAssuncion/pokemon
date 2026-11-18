<?php

declare(strict_types=1);

namespace Src\Forja\Domain\Piezas\Collections;

use Src\Forja\Domain\Piezas\DataTransferObjects\DefinicionPieza;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de definiciones de pieza.
 */
final class DefinicionesPiezaCollection extends Collection
{
    public string $type = DefinicionPieza::class;
}
