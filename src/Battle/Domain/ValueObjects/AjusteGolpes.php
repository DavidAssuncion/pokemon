<?php

declare(strict_types=1);

namespace Src\Battle\Domain\ValueObjects;

/**
 * Ajuste de golpes de un movimiento (Doble/Triple/Cuádruple Ataque).
 */
final readonly class AjusteGolpes
{
    public function __construct(
        public readonly int $golpes,
        public readonly float $fraccionPotencia,
    ) {
    }
}
