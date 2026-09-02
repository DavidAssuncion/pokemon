<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI\ValueObjects;

use Src\Battle\Domain\AccionBatalla;

/**
 * Resultado de una decisión de IA: acción elegida + contexto de evaluación para logging/debug.
 */
final readonly class ResultadoDecision
{
    /**
     * @param EvaluacionAmenaza[] $amenazas
     * @param EvaluacionAccion[]  $evaluaciones
     */
    public function __construct(
        public AccionBatalla $accion,
        public array $amenazas,
        public array $evaluaciones,
    ) {
    }
}
