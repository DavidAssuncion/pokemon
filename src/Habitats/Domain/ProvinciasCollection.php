<?php

declare(strict_types=1);

namespace Src\Habitats\Domain;

use Src\Shared\Domain\Collection;

class ProvinciasCollection extends Collection
{
    public string $type = ProvinceEntity::class;

    public function toArray(): array
    {
        return array_map(fn (ProvinceEntity $province) => $province->toArray(), $this->items);
    }
}
