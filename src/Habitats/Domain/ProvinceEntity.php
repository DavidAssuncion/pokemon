<?php

namespace Src\Habitats\Domain;

use Src\Habitats\Domain\HabitatsCollection;

class ProvinceEntity
{
    public function __construct(
        public int $id,
        public string $name,
        public HabitatsCollection $habitats
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'habitats' => $this->habitats->toArray(),
        ];
    }
}
