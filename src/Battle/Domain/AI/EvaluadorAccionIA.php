<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI;

use Illuminate\Support\Collection;
use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AI\ValueObjects\EvaluacionAccion;

/**
 * Interfaz para el evaluador de acciones de la IA.
 */
interface EvaluadorAccionIA
{
    /**
     * Evalúa una acción candidata y retorna su puntuación compuesta.
     */
    public function evaluar(
        ContextoDecisionIA $contexto,
        Collection $amenazas,
        AccionBatalla $accion,
    ): EvaluacionAccion;
}
