<?php

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\BattleAction;

interface DamageHandler
{
    public function setNext(DamageHandler $handler): DamageHandler;
    public function handle(BattleAction $action, float $daño): float;
}
