<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\AI\ValueObjects\EstimacionDanio;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\MovimientoBatalla;

/**
 * @deprecated Usar CalculadoraDanioIA directamente.
 */
class EstimadorDanioIAImpl implements EstimadorDanioIA
{
    public function __construct(
        private readonly CalculadoraDanioIA $calculadora,
    ) {
    }

    public function estimar(
        Combatiente $atacante,
        Combatiente $defensor,
        MovimientoBatalla $movimiento,
        AgregadoBatalla $batalla,
    ): EstimacionDanio {
        return $this->calculadora->estimar($atacante, $defensor, $movimiento, $batalla);
    }
}
