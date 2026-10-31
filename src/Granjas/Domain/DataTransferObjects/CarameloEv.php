<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\DataTransferObjects;

use App\Enums\StatEnum;

/**
 * Caramelos EV de un stat.
 */
final readonly class CarameloEv
{
    public function __construct(
        public StatEnum $stat,
        public int $cantidad,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'stat' => $this->stat->key(),
            'stat_id' => $this->stat->value,
            'cantidad' => $this->cantidad,
        ];
    }
}
