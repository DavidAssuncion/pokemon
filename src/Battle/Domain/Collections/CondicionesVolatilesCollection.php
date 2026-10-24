<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Collections;

use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de condiciones volátiles de un combatiente.
 */
final class CondicionesVolatilesCollection extends Collection
{
    public string $type = CondicionVolatil::class;

    /**
     * @return list<CondicionVolatil>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function buscar(ClaveCondicion $clave): ?CondicionVolatil
    {
        foreach ($this->items as $condicion) {
            if ($condicion->clave === $clave) {
                return $condicion;
            }
        }

        return null;
    }

    public function tiene(ClaveCondicion $clave): bool
    {
        return $this->buscar($clave) !== null;
    }

    public function quitar(ClaveCondicion $clave): void
    {
        $this->items = array_values(array_filter(
            $this->items,
            static fn (CondicionVolatil $condicion): bool => $condicion->clave !== $clave,
        ));
    }
}
