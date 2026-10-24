<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Capacidades;

/**
 * Habilidad que otorga una probabilidad de esquivar el ataque rival.
 */
interface ProbabilidadEsquiva
{
    /**
     * Probabilidad entre 0.0 y 1.0 (Evasion = 0.125).
     */
    public function probabilidadEsquiva(?\Src\Battle\Domain\Combatiente $portador = null): float;
}
