<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\DataTransferObjects;

use Src\Granjas\Domain\Collections\FamiliasGranjaCollection;

/**
 * Payload completo de la granja de un hábitat para la API/UI.
 */
final readonly class GranjaDetalle
{
    public function __construct(
        public int $id,
        public HabitatGranja $habitat,
        public FamiliasGranjaCollection $familias,
        public InventarioCaramelosGranja $inventario,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'habitat' => $this->habitat->toArray(),
            'familias' => $this->familias->toArray(),
            'inventario' => $this->inventario->toArray(),
        ];
    }
}
