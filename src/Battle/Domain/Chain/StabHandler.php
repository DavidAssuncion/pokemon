<?php

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\BattleAction;

class StabHandler extends AbstractDamageHandler
{
    protected function process(BattleAction $action, float $daño): float
    {
        foreach ($action->attacker->pokemon->tiposCollection as $tipo) {
            if ($tipo === $action->move->tipo) {
                return $daño * 1.5;
            }
        }

        return $daño;
    }
}
