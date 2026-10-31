<?php

declare(strict_types=1);

namespace Src\Huevos\Domain\DataTransferObjects;

/**
 * Datos de especie necesarios para poner un huevo.
 */
final readonly class EspecieHuevo
{
    public function __construct(
        public int $captureRate,
        public int $hatch,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'capture_rate' => $this->captureRate,
            'hatch' => $this->hatch,
        ];
    }
}
