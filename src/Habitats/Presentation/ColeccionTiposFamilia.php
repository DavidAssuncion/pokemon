<?php

declare(strict_types=1);

namespace Src\Habitats\Presentation;

use Src\Shared\Domain\Collection;

/**
 * Colección tipada de tipos pokémon de una familia evolutiva.
 */
final class ColeccionTiposFamilia extends Collection
{
    public string $type = DTOPokemonTipo::class;

    /**
     * Frontera: ids de tipo.
     *
     * @return list<int>
     */
    public function ids(): array
    {
        return $this->pluck(fn (DTOPokemonTipo $tipo) => $tipo->id);
    }

    /**
     * Frontera.
     *
     * @return list<array{id: int, name: string}>
     */
    public function toArray(): array
    {
        return $this->map(fn (DTOPokemonTipo $tipo) => $tipo->toArray());
    }
}
