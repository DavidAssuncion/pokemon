<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI\ValueObjects;

/**
 * Estimación de daño de un movimiento contra un objetivo.
 * En Fase 1: min = max = esperado (sin RNG), probabilidadKO binaria.
 */
final readonly class EstimacionDanio
{
    public function __construct(
        public float $minimo,
        public float $maximo,
        public float $esperado,
        public float $probabilidadKO,
    ) {
    }
}
