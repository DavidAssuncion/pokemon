<?php

declare(strict_types=1);

namespace Src\CombateRuta\Domain\DataTransferObjects;

use Src\Pokemon\Domain\Stats\DatosStats;

/**
 * Especie del pool de un hábitat con su peso de selección
 * (capture_rate/hatch). Permite estimar el PC rival esperado de ruta sin
 * exponer la composición ni duplicar la ponderación.
 */
final readonly class EspeciePonderadaRuta
{
    public function __construct(
        public DatosStats $stats,
        public float $peso,
    ) {
    }
}
