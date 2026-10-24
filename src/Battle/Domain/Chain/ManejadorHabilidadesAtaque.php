<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;

/**
 * Aplica los multiplicadores de daño de las habilidades del atacante
 * (Garra Dura, Enjambre, Acero Templado, Velo Arena, Depredador…).
 */
class ManejadorHabilidadesAtaque extends ManejadorDanioAbstracto
{
    protected function process(AccionBatalla $action, float $daño): float
    {
        if (! $action->attacker->estaVivo()) {
            return $daño;
        }

        return $daño
            * $action->attacker->multiplicadorDanioInfligido($action)
            * $action->attacker->multiplicadorDanioJefe();
    }
}
