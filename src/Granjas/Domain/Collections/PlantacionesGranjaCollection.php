<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\Collections;

use Src\Granjas\Domain\DataTransferObjects\PlantacionGranja;
use Src\Shared\Domain\Collection;

final class PlantacionesGranjaCollection extends Collection
{
    public string $type = PlantacionGranja::class;

    public function porSlot(int $slot): ?PlantacionGranja
    {
        /** @var PlantacionGranja|null $plantacion */
        $plantacion = $this->first(
            static fn (PlantacionGranja $item): bool => $item->slot === $slot,
        );

        return $plantacion;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return $this->map(
            static fn (PlantacionGranja $plantacion): array => $plantacion->toArray(),
        );
    }
}
