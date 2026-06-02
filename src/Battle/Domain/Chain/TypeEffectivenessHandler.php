<?php

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\BattleAction;

class TypeEffectivenessHandler extends AbstractDamageHandler
{
    protected function process(BattleAction $action, float $daño): float
    {
        $efectividad = $action->move->tipo->effectiveness($action->defender->pokemon);

        return $daño * $efectividad;
    }
}
