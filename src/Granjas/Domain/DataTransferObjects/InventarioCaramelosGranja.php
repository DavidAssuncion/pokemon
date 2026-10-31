<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\DataTransferObjects;

use Src\Granjas\Domain\Collections\CaramelosEvCollection;
use Src\Granjas\Domain\Collections\CaramelosFamiliaCollection;

/**
 * Inventario de caramelos relevante para la granja.
 */
final readonly class InventarioCaramelosGranja
{
    public function __construct(
        public CaramelosFamiliaCollection $familia,
        public CaramelosEvCollection $ev,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'familia' => $this->familia->toArray(),
            'ev' => $this->ev->toArray(),
        ];
    }
}
