<?php

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\BattleAction;

class DamageChain
{
    private AbstractDamageHandler $first;

    public function __construct()
    {
        $base = new BaseDamageHandler();
        $type = new TypeEffectivenessHandler();
        $stab = new StabHandler();
        $crit = new CriticalHandler();
        $position = new PositionHandler();

        $base->setNext($type);
        $type->setNext($stab);
        $stab->setNext($crit);
        $crit->setNext($position);

        $this->first = $base;
    }

    public function calculate(BattleAction $action): float
    {
        return max(1, floor($this->first->handle($action, 0)));
    }
}
