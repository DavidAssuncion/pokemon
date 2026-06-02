<?php

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\BattleAction;

class CriticalHandler extends AbstractDamageHandler
{
    protected function process(BattleAction $action, float $daño): float
    {
        $critChance = 0.0625;
        $critBonus = 1.5;

        return mt_rand() / mt_getrandmax() < $critChance
            ? $daño * $critBonus
            : $daño;
    }
}
