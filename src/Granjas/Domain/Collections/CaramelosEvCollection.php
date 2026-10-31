<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\Collections;

use App\Enums\StatEnum;
use Src\Granjas\Domain\DataTransferObjects\CarameloEv;
use Src\Shared\Domain\Collection;

final class CaramelosEvCollection extends Collection
{
    public string $type = CarameloEv::class;

    public function cantidadDe(StatEnum $stat): int
    {
        /** @var CarameloEv|null $caramelo */
        $caramelo = $this->first(
            static fn (CarameloEv $item): bool => $item->stat === $stat,
        );

        return $caramelo !== null ? $caramelo->cantidad : 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return $this->map(
            static fn (CarameloEv $caramelo): array => $caramelo->toArray(),
        );
    }
}
