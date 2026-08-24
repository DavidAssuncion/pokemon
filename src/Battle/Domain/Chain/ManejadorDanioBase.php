<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;

class ManejadorDanioBase extends ManejadorDanioAbstracto
{
    protected function process(AccionBatalla $action, float $daño): float
    {
        $move = $action->move;
        $nivel = 50;

        $atk = $move->esEspecial()
            ? $action->attacker->obtenerStatEfectivo('spAtk')
            : $action->attacker->obtenerStatEfectivo('attack');

        $def = $move->esEspecial()
            ? $action->defender->obtenerStatEfectivo('spDef')
            : $action->defender->obtenerStatEfectivo('defense');

        return (((2 * $nivel / 5 + 2) * $move->potencia * $atk / max($def, 1)) / 50) + 2;
    }
}
