<?php

declare(strict_types=1);

namespace Src\Battle\Domain\ValueObjects;

use Src\Battle\Domain\Enums\StatClave;

/**
 * Cambio de estadística declarado por un movimiento: par inmutable
 * (StatClave, factor) pendiente de aplicar sobre un Combatiente.
 *
 * Se construye normalmente desde etapas enteras ({@see desdeEtapas}), que
 * traduce 1:1 a factor multiplicativo con la tabla clásica −6..+6.
 */
final readonly class CambioStat
{
    public function __construct(
        public StatClave $stat,
        public float $factor,
    ) {
    }

    /**
     * Crea el cambio desde una etapa entera (−6..+6) usando la tabla exacta.
     */
    public static function desdeEtapas(StatClave $stat, int $etapas): self
    {
        return new self($stat, MultiplicadoresStats::factorDesdeStages($etapas));
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

    /**
     * Devuelve una nueva instancia de multiplicadores con este cambio aplicado.
     */
    public function aplicadoEn(MultiplicadoresStats $multiplicadores): MultiplicadoresStats
    {
        return $multiplicadores->aplicarFactor($this->stat, $this->factor);
    }
}
