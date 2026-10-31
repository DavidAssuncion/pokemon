<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\DataTransferObjects;

/**
 * Estado de mejora de una infraestructura.
 */
final readonly class MejoraNido
{
    public function __construct(
        public int $max,
        public ?int $coste,
        public bool $puede,
        public ?string $motivo,
    ) {
    }
}
