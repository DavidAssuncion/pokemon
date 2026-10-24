<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;

/**
 * Aplica los multiplicadores de daño de las habilidades del defensor
 * (Armadura Batalla, Escamas…).
 */
class ManejadorHabilidadesDefensa extends ManejadorDanioAbstracto
{
    protected function process(AccionBatalla $action, float $daño): float
    {
        if (! $action->defender->estaVivo() || $action->attacker->ignoraHabilidadRival()) {
            return $daño;
        }

        return $daño * $action->defender->multiplicadorDanioRecibido($action);
    }
}
