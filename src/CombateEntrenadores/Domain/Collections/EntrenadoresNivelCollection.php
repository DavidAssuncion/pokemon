<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\Domain\Collections;

use Src\CombateEntrenadores\Domain\DataTransferObjects\EntrenadoresNivel;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de niveles de entrenadores de un hábitat (orden 1..3).
 */
final class EntrenadoresNivelCollection extends Collection
{
    public string $type = EntrenadoresNivel::class;
}
