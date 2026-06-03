<?php

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;

class ManejadorCritico extends ManejadorDanioAbstracto
{
    protected function process(AccionBatalla $action, float $daño): float
    {
        $critChance = 0.0625;
        $critBonus = 1.5;

        return mt_rand() / mt_getrandmax() < $critChance
            ? $daño * $critBonus
            : $daño;
    }
}
