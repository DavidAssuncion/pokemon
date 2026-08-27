<?php

declare(strict_types=1);

namespace Src\Habitats\Presentation;

/**
 * DTO de respuesta tras asignar una familia a un hábitat.
 */
class DTOFamiliaAsignada
{
    public function __construct(
        public readonly int $habitatId,
        public readonly int $evolutionChainId,
        public readonly int $assignedCount,
    ) {
    }

    /**
     * @return array{habitat_id: int, evolution_chain_id: int, assigned_count: int}
     */
    public function toArray(): array
    {
        return [
            'habitat_id' => $this->habitatId,
            'evolution_chain_id' => $this->evolutionChainId,
            'assigned_count' => $this->assignedCount,
        ];
    }
}
