<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\Collections;

use Src\Granjas\Domain\DataTransferObjects\BayaGranja;
use Src\Shared\Domain\Collection;

final class BayasGranjaCollection extends Collection
{
    public string $type = BayaGranja::class;

    public function cantidadDe(string $clave): int
    {
        /** @var BayaGranja|null $baya */
        $baya = $this->first(
            static fn (BayaGranja $item): bool => $item->clave === $clave,
        );

        return $baya !== null ? $baya->cantidad : 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return $this->map(
            static fn (BayaGranja $baya): array => $baya->toArray(),
        );
    }
}
