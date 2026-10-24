<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Collections;

use Src\Battle\Domain\ValueObjects\EventoFaseJefe;
use Src\Shared\Domain\Collection;

final class EventosFaseJefeCollection extends Collection
{
    public string $type = EventoFaseJefe::class;
}
