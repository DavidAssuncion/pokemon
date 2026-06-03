<?php

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;

class ManejadorSTAB extends ManejadorDanioAbstracto
{
    protected function process(AccionBatalla $action, float $daño): float
    {
        foreach ($action->attacker->pokemon->tiposCollection as $tipo) {
            if ($tipo === $action->move->tipo) {
                return $daño * 1.5;
            }
        }

        return $daño;
    }
}
