<?php

declare(strict_types=1);

namespace Src\Battle\Domain\ValueObjects;

/**
 * Resultado de absorber daño con un sustituto.
 */
final readonly class ResultadoSustituto
{
    public function __construct(
        public readonly float $restante,
        public readonly float $absorbido,
        public readonly bool $roto,
    ) {
    }
}
