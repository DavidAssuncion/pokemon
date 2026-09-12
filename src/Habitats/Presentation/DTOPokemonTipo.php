<?php

declare(strict_types=1);

namespace Src\Habitats\Presentation;

/**
 * DTO tipado para un tipo pokémon de una familia evolutiva.
 */
final class DTOPokemonTipo
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
    ) {
    }

    /** @return array{id: int, name: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
