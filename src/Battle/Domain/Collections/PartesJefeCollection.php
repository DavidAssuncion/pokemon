<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Collections;

use Src\Battle\Domain\ValueObjects\ParteJefe;
use Src\Shared\Domain\Collection;

final class PartesJefeCollection extends Collection
{
    public string $type = ParteJefe::class;

    /**
     * @param  list<ParteJefe>  $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }

    /** @return list<ParteJefe> */
    public function all(): array
    {
        return $this->items;
    }
}
