<?php

declare(strict_types=1);

namespace Src\Equipos\Domain\Collections;

use Src\Equipos\Domain\TeamAggregate;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de equipos.
 */
final class TeamCollection extends Collection
{
    public string $type = TeamAggregate::class;

    /**
     * @param  list<TeamAggregate>  $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }

    /** @return list<TeamAggregate> */
    public function all(): array
    {
        return $this->items;
    }

    public function en(int $indice): ?TeamAggregate
    {
        $item = $this->items[$indice] ?? null;

        return $item instanceof TeamAggregate ? $item : null;
    }
}
