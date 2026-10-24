<?php

declare(strict_types=1);

namespace Src\Habitats\Domain\Collections;

use Src\Habitats\Domain\DataTransferObjects\EvolucionCadena;
use Src\Shared\Domain\Collection;

final class EvolucionCadenaCollection extends Collection
{
    public string $type = EvolucionCadena::class;

    /**
     * @param  list<EvolucionCadena>  $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }

    /** @return list<EvolucionCadena> */
    public function all(): array
    {
        return $this->items;
    }
}
