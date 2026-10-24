<?php

declare(strict_types=1);

namespace Src\Huevos\Domain\Collections;

use Src\Huevos\Domain\DataTransferObjects\HuevoResumen;
use Src\Shared\Domain\Collection;

final class HuevoResumenCollection extends Collection
{
    public string $type = HuevoResumen::class;

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return $this->map(fn (HuevoResumen $huevo): array => $huevo->toArray());
    }
}
