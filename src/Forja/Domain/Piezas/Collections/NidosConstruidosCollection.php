<?php

declare(strict_types=1);

namespace Src\Forja\Domain\Piezas\Collections;

use Src\Forja\Domain\Piezas\DataTransferObjects\NidoConstruido;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de nidos construidos del jugador.
 */
final class NidosConstruidosCollection extends Collection
{
    public string $type = NidoConstruido::class;
}
