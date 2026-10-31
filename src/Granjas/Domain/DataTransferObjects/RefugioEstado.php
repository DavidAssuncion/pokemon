<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\DataTransferObjects;

/**
 * Estado de un refugio dentro de un hábitat.
 */
final readonly class RefugioEstado
{
    public function __construct(
        public int $chainId,
        public int $nivel,
        public bool $insalubres,
    ) {
    }
}
