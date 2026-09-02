<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI\ValueObjects;

use Src\Battle\Domain\Combatiente;

/**
 * Evaluación de amenaza de un enemigo concreto.
 */
final readonly class EvaluacionAmenaza
{
    public function __construct(
        public Combatiente $enemigo,
        public float $amenazaOfensiva,
        public float $amenazaKO,
        public float $amenazaVelocidad,
        public float $amenazaSetup,
        public float $amenazaEstrategica,
        public float $score,
    ) {
    }
}
