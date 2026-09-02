<?php

declare(strict_types=1);

namespace Src\Gimnasios\Domain\Collections;

use Src\Shared\Domain\Collection;

/**
 * Colección de enteros (species_id). Src\Shared\Domain\Collection valida con
 * `instanceof`, que no aplica a escalares; se valida con is_int().
 */
final class IntCollection extends Collection
{
    public string $type = 'int';

    protected function validateType($item): void
    {
        if (! is_int($item)) {
            throw new \InvalidArgumentException('Invalid type');
        }
    }

    /** @return list<int> */
    public function all(): array
    {
        return $this->items;
    }
}
