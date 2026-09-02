<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI\ValueObjects;

use Illuminate\Support\Collection;
use Src\Battle\Domain\AccionBatalla;

/**
 * Resultado de una decisión de IA: acción elegida + contexto de evaluación para logging/debug.
 */
final readonly class ResultadoDecision
{
    /**
     * @param Collection<int, EvaluacionAmenaza> $amenazas
     * @param Collection<int, EvaluacionAccion> $evaluaciones
     */
    public function __construct(
        public AccionBatalla $accion,
        public Collection $amenazas,
        public Collection $evaluaciones,
    ) {
    }
}
