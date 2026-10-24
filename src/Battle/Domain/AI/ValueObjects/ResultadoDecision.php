<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI\ValueObjects;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AI\Collections\EvaluacionAmenazaCollection;

/**
 * Resultado de una decisión de IA: acción elegida + contexto de evaluación para logging/debug.
 */
final readonly class ResultadoDecision
{
    /**
     * @param EvaluacionAccion[] $evaluaciones
     */
    public function __construct(
        public AccionBatalla $accion,
        public EvaluacionAmenazaCollection $amenazas,
        public array $evaluaciones,
    ) {
    }
}
