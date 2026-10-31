<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\DataTransferObjects;

use Src\Pokemon\Domain\Stats\StatsValue;

/**
 * Bonos calculados de una familia dentro de una granja concreta.
 */
final readonly class BonosFamilia
{
    public function __construct(
        public NidoBonos $nido,
        public RefugioBonos $refugio,
        public StatsValue $evsFamilia,
    ) {
    }
}
