<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\DataTransferObjects;

use App\Enums\StatEnum;

/**
 * Nivel de complemento de un stat concreto.
 */
final readonly class NivelComplemento
{
    public function __construct(
        public StatEnum $stat,
        public int $nivel,
    ) {
    }
}
