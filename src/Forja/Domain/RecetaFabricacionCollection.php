<?php

declare(strict_types=1);

namespace Src\Forja\Domain;

use Src\Shared\Domain\Collection;

/**
 * Colección tipada de recetas de fabricación.
 */
final class RecetaFabricacionCollection extends Collection
{
    public string $type = RecetaFabricacion::class;
}
