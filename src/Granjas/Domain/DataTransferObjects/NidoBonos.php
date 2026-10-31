<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\DataTransferObjects;

use Src\Pokemon\Domain\Stats\RangoIvs;

/**
 * Bonos del nido de una familia.
 */
final readonly class NidoBonos
{
    public function __construct(
        public int $nivel,
        public RangoIvs $rangoIvs,
        public float $factorTiempo,
    ) {
    }
}
