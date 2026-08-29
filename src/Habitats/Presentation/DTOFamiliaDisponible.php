<?php

declare(strict_types=1);

namespace Src\Habitats\Presentation;

/**
 * DTO que representa una familia evolutiva disponible en un hábitat.
 */
class DTOFamiliaDisponible
{
    /**
     * @param  array{id: int, name: string, icon: string, level: int}  $base
     * @param  array<int, array{id: int, name: string, icon: string, level: int}>  $evolutions
     * @param  array<int, array{id: int, name: string}>  $types
     */
    public function __construct(
        public readonly int $evolutionChainId,
        public readonly array $base,
        public readonly array $evolutions,
        public readonly array $types = [],
    ) {
    }

    /**
     * @return array{evolution_chain_id: int, base: array{id: int, name: string, icon: string, level: int}, evolutions: array<int, array{id: int, name: string, icon: string, level: int}>, types: array<int, array{id: int, name: string}>}
     */
    public function toArray(): array
    {
        return [
            'evolution_chain_id' => $this->evolutionChainId,
            'base' => $this->base,
            'evolutions' => $this->evolutions,
            'types' => $this->types,
        ];
    }
}
