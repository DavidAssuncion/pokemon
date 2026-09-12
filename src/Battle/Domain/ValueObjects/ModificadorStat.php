<?php

declare(strict_types=1);

namespace Src\Battle\Domain\ValueObjects;

use Src\Battle\Domain\Enums\StatClave;

/**
 * Par inmutable (StatClave, factor) que representa el modificador
 * multiplicativo aplicado a una estadística concreta.
 */
final readonly class ModificadorStat
{
    public function __construct(
        public StatClave $stat,
        public float $factor,
    ) {
    }

    /**
     * Valor string de la clave de estadística (ej: 'attack').
     */
    public function statValue(): string
    {
        return $this->stat->value;
    }

    /**
     * Porcentaje de cambio respecto a 1.0 (ej: factor 1.15 → +15%).
     */
    public function porcentaje(): float
    {
        return ($this->factor - 1.0) * 100;
    }
}
