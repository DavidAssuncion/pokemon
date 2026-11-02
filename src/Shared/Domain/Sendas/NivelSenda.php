<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Sendas;

/**
 * Nivel de una Senda de tipo.
 *
 * Los niveles 1-3 son visibles y desbloquean movimientos; los niveles 4-5 son
 * maestrías pasivas. `visible()` comprime 4-5 a 3 para presentación.
 */
enum NivelSenda: int
{
    case N1 = 1;
    case N2 = 2;
    case N3 = 3;
    case N4 = 4;
    case N5 = 5;

    /**
     * Nivel a partir de un entero, acotado al rango 1-5.
     */
    public static function desdeInt(int $nivel): self
    {
        return self::from(max(1, min(5, $nivel)));
    }

    /**
     * Nivel visible para desbloqueo y presentación: min(3, nivel).
     */
    public function visible(): int
    {
        return min(3, $this->value);
    }

    /**
     * Indica si el nivel aporta beneficios pasivos (niveles 4 y 5).
     */
    public function tienePasivos(): bool
    {
        return $this->value >= 4;
    }
}
