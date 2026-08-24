<?php

declare(strict_types=1);

namespace Src\Habitats\Domain;

class ProvinceEntity
{
    public function __construct(
        public int $id,
        public string $name,
        public HabitatsCollection $habitats
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'habitats' => $this->habitats->toArray(),
        ];
    }
}
