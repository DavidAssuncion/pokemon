<?php

declare(strict_types=1);

namespace Src\Granjas\App;

use Src\Granjas\Domain\Collections\BayasGranjaCollection;

/**
 * Catálogo completo de bayas con las existencias del jugador, incluidas las
 * agotadas, previa validación de que el hábitat existe.
 */
final class ObtenerCatalogoBayas
{
    public function __construct(
        private readonly AsegurarGranja $asegurarGranja,
        private readonly InventarioGranja $inventario,
    ) {
    }

    public function handle(int $userId, int $habitatId): BayasGranjaCollection
    {
        $this->asegurarGranja->para($userId, $habitatId);

        return $this->inventario->catalogoBayas($userId);
    }
}
