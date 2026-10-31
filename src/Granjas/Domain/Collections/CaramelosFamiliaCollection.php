<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\Collections;

use Src\Granjas\Domain\DataTransferObjects\CarameloFamilia;
use Src\Shared\Domain\Collection;

final class CaramelosFamiliaCollection extends Collection
{
    public string $type = CarameloFamilia::class;

    public function cantidadDe(int $chainId): int
    {
        /** @var CarameloFamilia|null $caramelo */
        $caramelo = $this->first(
            static fn (CarameloFamilia $item): bool => $item->chainId === $chainId,
        );

        return $caramelo !== null ? $caramelo->cantidad : 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return $this->map(
            static fn (CarameloFamilia $caramelo): array => $caramelo->toArray(),
        );
    }
}
