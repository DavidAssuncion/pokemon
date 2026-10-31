<?php

declare(strict_types=1);

namespace Src\Pokemon\Domain\Stats;

/**
 * Rango de IVs por stat.
 */
final readonly class RangoIvs
{
    public function __construct(
        public int $min,
        public int $max,
    ) {
    }

    /** @return array{min: int, max: int} */
    public function toArray(): array
    {
        return ['min' => $this->min, 'max' => $this->max];
    }
}
