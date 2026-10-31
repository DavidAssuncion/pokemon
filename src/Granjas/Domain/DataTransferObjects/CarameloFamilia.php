<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\DataTransferObjects;

/**
 * Caramelos de una familia evolutiva.
 */
final readonly class CarameloFamilia
{
    public function __construct(
        public int $chainId,
        public int $cantidad,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'evolution_chain_id' => $this->chainId,
            'cantidad' => $this->cantidad,
        ];
    }
}
