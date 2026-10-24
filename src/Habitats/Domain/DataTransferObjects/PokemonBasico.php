<?php

declare(strict_types=1);

namespace Src\Habitats\Domain\DataTransferObjects;

/**
 * Pokémon mínimo (id, nombre) para listados de hábitat.
 */
final readonly class PokemonBasico
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
    ) {
    }

    /**
     * Frontera — shape exacto del contrato JSON previo.
     *
     * @return array{id: int, name: string}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }
}
