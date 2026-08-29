<?php

declare(strict_types=1);

namespace Src\Habitats\Presentation;

use Src\Shared\Domain\Collection;

/**
 * DTO que representa la colección de familias disponibles en un hábitat.
 */
class DTOFamiliasDisponibles extends Collection
{
    public string $type = DTOFamiliaDisponible::class;

    /** @return array<int, DTOFamiliaDisponible> */
    public function all(): array
    {
        return $this->items;
    }

    public function get(int $index): ?DTOFamiliaDisponible
    {
        return $this->items[$index] ?? null;
    }

    /**
     * @return array<int, array{evolution_chain_id: int, base: array{id: int, name: string, icon: string, level: int}, evolutions: array<int, array{id: int, name: string, icon: string, level: int}>, types: array<int, array{id: int, name: string}>}>
     */
    public function toArray(): array
    {
        return array_map(fn (DTOFamiliaDisponible $f) => $f->toArray(), $this->items);
    }
}
