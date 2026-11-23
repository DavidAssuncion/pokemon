<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\Domain\Collections;

use Src\HabitatsTemporales\Domain\OleadaRival;
use Src\Shared\Domain\Collection;

/**
 * Colección ordenada de oleadas de una facción.
 */
final class OleadaRivalCollection extends Collection
{
    public string $type = OleadaRival::class;
}
