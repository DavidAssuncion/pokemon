<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\DataTransferObjects;

/**
 * Coste en caramelos de una mejora.
 */
final readonly class CosteCaramelos
{
    public function __construct(
        public int $ev,
        public int $familia,
    ) {
    }
}
