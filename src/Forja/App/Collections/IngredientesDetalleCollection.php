<?php

declare(strict_types=1);

namespace Src\Forja\App\Collections;

use Src\Forja\App\DataTransferObjects\IngredienteDetalle;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de ingredientes detallados.
 */
final class IngredientesDetalleCollection extends Collection
{
    public string $type = IngredienteDetalle::class;
}
