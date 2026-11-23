<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\Domain\Collections;

use Src\HabitatsTemporales\Domain\CombateRival;
use Src\Shared\Domain\Collection;

/**
 * Colección ordenada de combates de una oleada.
 */
final class CombateRivalCollection extends Collection
{
    public string $type = CombateRival::class;
}
