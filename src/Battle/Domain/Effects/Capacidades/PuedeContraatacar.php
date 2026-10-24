<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Capacidades;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;

/**
 * Habilidad o buff que devuelve parte del daño recibido como contraataque.
 */
interface PuedeContraatacar
{
    public function contraatacar(
        Combatiente $portador,
        AccionBatalla $accion,
        float $danoRecibido,
        AgregadoBatalla $battle,
    ): void;
}
