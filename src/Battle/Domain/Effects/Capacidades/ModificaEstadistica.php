<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Capacidades;

use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\StatClave;

/**
 * Habilidad que altera una estadística efectiva de su portador.
 */
interface ModificaEstadistica
{
    /**
     * Factor multiplicativo sobre el stat base (1.0 = sin cambio). Se aplica
     * antes de las etapas y de la penalización por parálisis.
     */
    public function multiplicadorEstadistica(Combatiente $portador, StatClave $estadistica): float;
}
