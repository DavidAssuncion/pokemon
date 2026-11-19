<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Capacidades;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;

/**
 * Efectos que reaccionan cuando su portador pierde la acción del turno.
 *
 * La invocación es idempotente: los puntos de despacho pueden repetirla sin
 * provocar efectos adicionales.
 */
interface ReaccionaAlPerderTurno
{
    public function alPerderTurno(Combatiente $portador, AgregadoBatalla $battle): void;
}
