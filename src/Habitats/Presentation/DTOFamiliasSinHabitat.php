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

    public function contieneSpecies(int $speciesId): bool
    {
        return $this->contains(fn (DTOFamiliaSinHabitat $familia) => $familia->contieneSpecies($speciesId));
    }

    /**
     * Ordena las familias por el species_id mínimo de sus miembros (el
     * "primer integrante" de cada familia, criterio de negocio).
     */
    public function ordenadasPorMinSpeciesId(): static
    {
        $items = $this->items;
        usort($items, fn (DTOFamiliaSinHabitat $a, DTOFamiliaSinHabitat $b): int => $a->minSpeciesId() <=> $b->minSpeciesId());

        return new static($items);
    }

    /**
     * @return array<int, array{evolution_chain_id: int, base: array{id: int, name: string, icon: string}, evolutions: array<int, array{id: int, name: string, icon: string}>, types: array<int, array{id: int, name: string}>}>
     */
    public function toArray(): array
    {
        return $this->map(fn (DTOFamiliaSinHabitat $f) => $f->toArray());
    }
}
