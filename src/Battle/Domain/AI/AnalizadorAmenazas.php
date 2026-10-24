<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI;

use Src\Battle\Domain\AI\Collections\EvaluacionAmenazaCollection;

/**
 * Interfaz para el analizador de amenazas de la IA.
 */
interface AnalizadorAmenazas
{
    /**
     * Analiza la amenaza de cada enemigo vivo contra los aliados del actor.
     */
    public function analizar(ContextoDecisionIA $contexto): EvaluacionAmenazaCollection;
}
