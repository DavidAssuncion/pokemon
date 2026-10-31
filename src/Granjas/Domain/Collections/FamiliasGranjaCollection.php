<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\Collections;

use Src\Granjas\Domain\DataTransferObjects\FamiliaGranja;
use Src\Shared\Domain\Collection;

final class FamiliasGranjaCollection extends Collection
{
    public string $type = FamiliaGranja::class;

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return $this->map(
            static fn (FamiliaGranja $familia): array => $familia->toArray(),
        );
    }
}
