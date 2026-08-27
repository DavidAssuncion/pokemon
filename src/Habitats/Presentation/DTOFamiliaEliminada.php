<?php

declare(strict_types=1);

namespace Src\Habitats\Presentation;

/**
 * DTO de respuesta tras eliminar una familia de un hábitat.
 */
class DTOFamiliaEliminada
{
    public function __construct(
        public readonly int $habitatId,
        public readonly int $evolutionChainId,
        public readonly int $removedCount,
    ) {
    }

    /**
     * @return array{habitat_id: int, evolution_chain_id: int, removed_count: int}
     */
    public function toArray(): array
    {
        return [
            'habitat_id' => $this->habitatId,
            'evolution_chain_id' => $this->evolutionChainId,
            'removed_count' => $this->removedCount,
        ];
    }
}
