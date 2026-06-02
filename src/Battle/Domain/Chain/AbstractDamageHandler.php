<?php

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\BattleAction;

abstract class AbstractDamageHandler implements DamageHandler
{
    private ?DamageHandler $next = null;

    public function setNext(DamageHandler $handler): DamageHandler
    {
        $this->next = $handler;
        return $handler;
    }

    public function handle(BattleAction $action, float $daño): float
    {
        $daño = $this->process($action, $daño);

        if ($this->next !== null) {
            return $this->next->handle($action, $daño);
        }

        return $daño;
    }

    abstract protected function process(BattleAction $action, float $daño): float;
}
