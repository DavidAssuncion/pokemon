<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\AI\ValueObjects\EstimacionDanio;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\MovimientoBatalla;

/**
 * Interfaz para el servicio de estimación de daño de la IA.
 *
 * @deprecated Usar CalculadoraDanioIA directamente. Se mantiene por compatibilidad.
 */
interface EstimadorDanioIA
{
    public function estimar(
        Combatiente $atacante,
        Combatiente $defensor,
        MovimientoBatalla $movimiento,
        AgregadoBatalla $batalla,
    ): EstimacionDanio;
}
