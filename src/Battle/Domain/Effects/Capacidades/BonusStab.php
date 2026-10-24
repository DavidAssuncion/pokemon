<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Capacidades;

use Src\Battle\Domain\AccionBatalla;

/**
 * Habilidad que otorga STAB adicional (Vacío, Guardián de los mares) o
 * cambia el tipo efectivo del movimiento (Alma Dragón).
 */
interface BonusStab
{
    /**
     * Factor multiplicativo final del STAB (>1.0 para bonificar). El motor lo
     * aplica además del STAB normal si el movimiento coincide con el tipo.
     */
    public function multiplicadorStab(AccionBatalla $accion): float;
}
