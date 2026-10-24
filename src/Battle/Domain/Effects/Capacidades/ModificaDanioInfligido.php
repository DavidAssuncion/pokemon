<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Capacidades;

use Src\Battle\Domain\AccionBatalla;

/**
 * Habilidad que modifica el daño que su portador inflige.
 */
interface ModificaDanioInfligido
{
    /**
     * Factor multiplicativo (1.0 = sin cambio). Se aplica después del clima
     * y antes de los objetos equipados.
     */
    public function multiplicadorDanioInfligido(AccionBatalla $accion): float;
}
