<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\DataTransferObjects;

/**
 * Estado de mejora de un complemento EV.
 */
final readonly class MejoraComplemento
{
    public function __construct(
        public int $max,
        public ?CosteCaramelos $coste,
        public bool $puede,
        public ?string $motivo,
    ) {
    }
}
