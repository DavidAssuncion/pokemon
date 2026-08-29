<?php

declare(strict_types=1);

namespace Src\Habitats\Presentation;

use Src\Shared\Domain\Collection;

/**
 * DTO que representa la colección de familias sin hábitat asignado.
 */
class DTOFamiliasSinHabitat extends Collection
{
    public string $type = DTOFamiliaSinHabitat::class;

    /** @return array<int, DTOFamiliaSinHabitat> */
    public function all(): array
    {
        return $this->items;
    }

    public function get(int $index): ?DTOFamiliaSinHabitat
    {
        return $this->items[$index] ?? null;
    }

    /**
     * @return array<int, array{evolution_chain_id: int, base: array{id: int, name: string, icon: string}, evolutions: array<int, array{id: int, name: string, icon: string}>, types: array<int, array{id: int, name: string}>}>
     */
    public function toArray(): array
    {
        return array_map(fn (DTOFamiliaSinHabitat $f) => $f->toArray(), $this->items);
    }
}
