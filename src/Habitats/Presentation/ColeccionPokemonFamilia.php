<?php

declare(strict_types=1);

namespace Src\Habitats\Presentation;

use Src\Shared\Collections\IntCollection;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de miembros de una familia evolutiva (base + evoluciones).
 */
final class ColeccionPokemonFamilia extends Collection
{
    public string $type = DTOPokemonFamilia::class;

    /**
     * ids de species de todos los miembros.
     */
    public function speciesIds(): IntCollection
    {
        return new IntCollection($this->pluck(fn (DTOPokemonFamilia $p) => $p->speciesId));
    }

    /**
     * El "primer integrante" de la familia: el de menor species_id.
     */
    public function primeraEvolucion(): ?DTOPokemonFamilia
    {
        return $this->reduce(
            fn (?DTOPokemonFamilia $menor, DTOPokemonFamilia $actual) => $menor === null || $actual->speciesId < $menor->speciesId
                ? $actual
                : $menor,
            null,
        );
    }

    public function porId(int $id): ?DTOPokemonFamilia
    {
        return $this->reduce(
            fn (?DTOPokemonFamilia $encontrado, DTOPokemonFamilia $actual) => $actual->id === $id ? $actual : $encontrado,
            null,
        );
    }

    /**
     * Frontera.
     *
     * @return list<array{id: int, name: string, icon: string, level?: int}>
     */
    public function toArray(): array
    {
        return $this->map(fn (DTOPokemonFamilia $p) => $p->toArray());
    }
}
