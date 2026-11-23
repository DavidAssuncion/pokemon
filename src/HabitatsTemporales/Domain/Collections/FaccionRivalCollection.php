<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\Domain\Collections;

use Src\HabitatsTemporales\Domain\FaccionRival;
use Src\Shared\Domain\Collection;

/**
 * Colección de facciones rivales del catálogo.
 */
final class FaccionRivalCollection extends Collection
{
    public string $type = FaccionRival::class;
}
