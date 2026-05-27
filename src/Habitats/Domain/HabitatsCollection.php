<?php

namespace Src\Habitats\Domain;

use Src\Habitats\Domain\HabitatEntity;
use Src\Shared\Domain\Collection;

class HabitatsCollection extends Collection
{
    public string $type = HabitatEntity::class;

    public function toArray(): array
    {
        return array_map(fn(HabitatEntity $habitat) => $habitat->toArray(), $this->items);
    }
}
