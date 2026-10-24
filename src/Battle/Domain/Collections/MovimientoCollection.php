<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Collections;

use Src\Battle\Domain\Movimiento;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de movimientos del catálogo.
 */
final class MovimientoCollection extends Collection
{
    public string $type = Movimiento::class;

    /**
     * @return list<Movimiento>
     */
    public function all(): array
    {
        return $this->items;
    }
}
