<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI\ValueObjects;

use Src\Battle\Domain\AgregadoBatalla;

/**
 * Resultado de simular una acción sobre un clon del estado de batalla.
 * No muta la batalla original.
 */
final readonly class ResultadoSimulacion
{
    public function __construct(
        public AgregadoBatalla $estadoSimulado,
        public float $danoInfligido,
        public bool $objetivoDerrotado,
        public string $actorId,
        public string $objetivoId,
    ) {
    }
}
