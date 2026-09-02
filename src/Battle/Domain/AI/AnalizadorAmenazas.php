<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI;

use Src\Battle\Domain\AI\ValueObjects\EvaluacionAmenaza;

/**
 * Interfaz para el analizador de amenazas de la IA.
 */
interface AnalizadorAmenazas
{
    /**
     * Analiza la amenaza de cada enemigo vivo contra los aliados del actor.
     *
     * @return EvaluacionAmenaza[]
     */
    public function analizar(ContextoDecisionIA $contexto): array;
}
