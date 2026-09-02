<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AI\ValueObjects\EvaluacionAccion;
use Src\Battle\Domain\AI\ValueObjects\EvaluacionAmenaza;

/**
 * Interfaz para el evaluador de acciones de la IA.
 */
interface EvaluadorAccionIA
{
    /**
     * Evalúa una acción candidata y retorna su puntuación compuesta.
     *
     * @param  EvaluacionAmenaza[]  $amenazas
     */
    public function evaluar(
        ContextoDecisionIA $contexto,
        array $amenazas,
        AccionBatalla $accion,
    ): EvaluacionAccion;
}
