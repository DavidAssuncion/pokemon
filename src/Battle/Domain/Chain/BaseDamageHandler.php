<?php

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\BattleAction;

class BaseDamageHandler extends AbstractDamageHandler
{
    protected function process(BattleAction $action, float $daño): float
    {
        $move = $action->move;
        $nivel = 50;

        $atk = $move->esEspecial()
            ? $action->attacker->pokemon->battleStats->spAtk
            : $action->attacker->pokemon->battleStats->attack;

        $def = $move->esEspecial()
            ? $action->defender->pokemon->battleStats->spDef
            : $action->defender->pokemon->battleStats->defense;

        return ((((2 * $nivel / 5 + 2) * $move->potencia * $atk / max($def, 1)) / 50) + 2);
    }
}
