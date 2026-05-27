<?php

namespace Src\Habitats\Domain;

class HabitatEntity
{
    public function __construct(
        public int $id,
        public string $name,
        public int $provinceId
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'province_id' => $this->provinceId,
        ];
    }
}
