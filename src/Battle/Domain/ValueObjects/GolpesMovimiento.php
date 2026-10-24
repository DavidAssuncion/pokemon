<?php

declare(strict_types=1);

namespace Src\Battle\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Número de golpes de un movimiento: fijo (min = max) o aleatorio en un rango.
 */
final readonly class GolpesMovimiento
{
    public function __construct(
        public int $minimo = 1,
        public int $maximo = 1,
    ) {
        if ($this->minimo < 1 || $this->maximo < $this->minimo) {
            throw new InvalidArgumentException('Rango de golpes inválido');
        }
    }

    public static function fijo(int $golpes): self
    {
        return new self($golpes, $golpes);
    }

    public function esFijo(): bool
    {
        return $this->minimo === $this->maximo;
    }

    public function esMultiple(): bool
    {
        return $this->maximo > 1;
    }
}
