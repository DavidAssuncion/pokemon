<?php

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\BattleAction;
use Src\Battle\Domain\Position;

class PositionHandler extends AbstractDamageHandler
{
    protected function process(BattleAction $action, float $daño): float
    {
        if ($action->defender->estaEnRetaguardia() && $action->defenderTeamHasVanguard) {
            return $daño * 0.5;
        }

        return $daño;
    }
}
