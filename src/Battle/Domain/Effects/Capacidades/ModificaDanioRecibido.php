<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Capacidades;

use Src\Battle\Domain\AccionBatalla;

/**
 * Habilidad que modifica el daño que su portador recibe.
 */
interface ModificaDanioRecibido
{
    /**
     * Factor multiplicativo (1.0 = sin cambio). Se aplica al daño bruto antes
     * de repartirlo entre escudo y HP.
     */
    public function multiplicadorDanioRecibido(AccionBatalla $accion): float;
}
