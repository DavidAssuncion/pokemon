<?php

namespace Src\Battle\Domain\Observer;

use Src\Battle\Domain\Combatant;

class EndTurnHealObserver implements BattleObserver
{
    public function onEndTurn(Combatant $combatant): void {}
    public function onDamaged(Combatant $target, float $daño): void {}
    public function onHealed(Combatant $target, float $cantidad): void {}
    public function onFainted(Combatant $combatant): void {}
}
