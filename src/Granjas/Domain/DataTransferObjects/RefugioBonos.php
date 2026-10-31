<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\DataTransferObjects;

/**
 * Bonos del refugio de una familia.
 */
final readonly class RefugioBonos
{
    public function __construct(
        public int $nivel,
        public bool $insalubres,
        public float $factorAparicion,
        public float $factorCaptura,
    ) {
    }
}
