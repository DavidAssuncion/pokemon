<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Capacidades;

/**
 * Habilidad que altera la probabilidad de crítico del portador (Predicción).
 */
interface AjustaCritico
{
    /**
     * Factor multiplicativo sobre la probabilidad base (1.0 = sin cambio).
     */
    public function multiplicadorProbabilidadCritico(): float;
}
