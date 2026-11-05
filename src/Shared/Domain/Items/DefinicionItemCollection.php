<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Items;

use Src\Shared\Domain\Collection;

/**
 * Colección tipada de definiciones de item.
 */
final class DefinicionItemCollection extends Collection
{
    public string $type = DefinicionItem::class;
}
