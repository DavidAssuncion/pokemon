<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;

class ManejadorPosicion extends ManejadorDanioAbstracto
{
    protected function process(AccionBatalla $action, float $daño): float
    {
        if ($action->defender->estaEnRetaguardia() && $action->defenderTeamHasVanguard) {
            return $daño * 0.5;
        }

        return $daño;
    }
}
