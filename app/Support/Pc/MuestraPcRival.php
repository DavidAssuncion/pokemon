<?php

declare(strict_types=1);

namespace App\Support\Pc;

use Src\Pokemon\Domain\Stats\StatsValue;

/**
 * Muestra de una especie del pool rival para el cálculo del PC esperado.
 */
final readonly class MuestraPcRival
{
    public function __construct(
        public StatsValue $base,
        public StatsValue $evs,
        public StatsValue $ivs,
        public int $nivel,
        public float $peso,
    ) {
    }
}
