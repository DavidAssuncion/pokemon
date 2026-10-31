<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\DataTransferObjects;

use Src\Granjas\Domain\Collections\RefugiosHabitatCollection;

/**
 * Contexto compartido para construir la vista de una familia.
 */
final readonly class ContextoFamiliaGranja
{
    public function __construct(
        public RefugiosHabitatCollection $refugios,
        public InventarioCaramelosGranja $inventario,
    ) {
    }
}
