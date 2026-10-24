<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;

/**
 * Aplica los efectos de equipo del defensor (Pantalla de Luz / Reflejo).
 */
class ManejadorEquipo extends ManejadorDanioAbstracto
{
    protected function process(AccionBatalla $action, float $daño): float
    {
        if ($action->equipoDefensor === null) {
            return $daño;
        }

        return $daño * $action->equipoDefensor->multiplicadorDanoRecibido($action);
    }
}
