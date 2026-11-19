<?php

declare(strict_types=1);

namespace Src\Habitats\Presentation;

use Src\Shared\Collections\IntCollection;
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

    public function contieneSpecies(int $speciesId): bool
    {
        return $this->contains(fn (DTOFamiliaDisponible $familia) => $familia->contieneSpecies($speciesId));
    }

    /**
     * Ordena las familias por el species_id mínimo de sus miembros (el
     * "primer integrante" de cada familia, criterio de negocio).
     */
    public function ordenadasPorMinSpeciesId(): static
    {
        $items = $this->items;
        usort($items, fn (DTOFamiliaDisponible $a, DTOFamiliaDisponible $b): int => $a->minSpeciesId() <=> $b->minSpeciesId());

        return new static($items);
    }

    /**
     * Ids de pokémon (base + evoluciones) de todas las familias, sin repetir.
     */
    public function pokemonIds(): IntCollection
    {
        $ids = [];

        foreach ($this->all() as $familia) {
            $ids[$familia->base->id] = $familia->base->id;

            foreach ($familia->evolutions as $evolucion) {
                $ids[$evolucion->id] = $evolucion->id;
            }
        }

        return new IntCollection(array_values($ids));
    }

    /**
     * Ids de cadena evolutiva de todas las familias, sin repetir y en orden de
     * entrada.
     */
    public function evolutionChainIds(): IntCollection
    {
        $cadenas = [];

        foreach ($this->all() as $familia) {
            $cadenas[$familia->evolutionChainId] = $familia->evolutionChainId;
        }

        return new IntCollection(array_values($cadenas));
    }

    /**
     * @return array<int, array{evolution_chain_id: int, base: array{id: int, name: string, icon: string, level: int}, evolutions: array<int, array{id: int, name: string, icon: string, level: int}>, types: array<int, array{id: int, name: string}>}>
     */
    public function toArray(): array
    {
        return $this->map(fn (DTOFamiliaDisponible $f) => $f->toArray());
    }
}
