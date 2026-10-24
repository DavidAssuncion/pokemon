<?php

declare(strict_types=1);

namespace Src\Battle\Domain\ValueObjects;

/**
 * Resultado desglosado de aplicar daño a un combatiente.
 *
 * Separa lo que absorbe la barrera (física o especial) de lo que llega a HP,
 * e indica si la barrera se rompió en este impacto. Es el contrato que usan
 * los efectos reactivos (Bromista, Demoledor, Represión Metal…).
 */
final readonly class ResultadoDanio
{
    public function __construct(
        public float $danoHp = 0.0,
        public float $danoEscudo = 0.0,
        public float $excedente = 0.0,
        public bool $rompioEscudo = false,
    ) {
    }

    public static function sinDano(): self
    {
        return new self();
    }

    /**
     * Daño total que sale del cálculo: HP + escudo absorbido.
     */
    public function danoAplicado(): float
    {
        return $this->danoHp + $this->danoEscudo;
    }

    public function huboDano(): bool
    {
        return $this->danoHp > 0.0 || $this->danoEscudo > 0.0;
    }
}
