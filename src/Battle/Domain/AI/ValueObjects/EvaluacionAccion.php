<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI\ValueObjects;

use Src\Battle\Domain\AccionBatalla;

/**
 * Evaluación de una acción candidata (atacante + movimiento + objetivo).
 */
final readonly class EvaluacionAccion
{
    public function __construct(
        public AccionBatalla $accion,
        public float $score,
        public float $koValue,
        public float $damageValue,
        public float $threatReduction,
        public float $survivalValue,
        public float $risk,
    ) {
    }
}
