<?php

declare(strict_types=1);

namespace Src\Forja\App\Collections;

use Src\Forja\App\DataTransferObjects\RecetaDetalle;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de recetas detalladas.
 */
final class RecetaDetalleCollection extends Collection
{
    public string $type = RecetaDetalle::class;
}
