<?php

declare(strict_types=1);

namespace Src\Huevos\Domain\Collections;

use Src\Huevos\Domain\DataTransferObjects\HuevoDisponible;
use Src\Shared\Domain\Collection;

final class HuevoDisponibleCollection extends Collection
{
    public string $type = HuevoDisponible::class;

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return $this->map(fn (HuevoDisponible $huevo): array => $huevo->toArray());
    }
}
