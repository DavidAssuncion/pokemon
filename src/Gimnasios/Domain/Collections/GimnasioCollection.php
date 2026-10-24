<?php

declare(strict_types=1);

namespace Src\Gimnasios\Domain\Collections;

use Src\Gimnasios\Domain\Gimnasio;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de gimnasios.
 */
final class GimnasioCollection extends Collection
{
    public string $type = Gimnasio::class;

    /**
     * @param  list<Gimnasio>  $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }

    /** @return list<Gimnasio> */
    public function all(): array
    {
        return $this->items;
    }
}
