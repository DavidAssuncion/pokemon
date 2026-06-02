<?php

namespace Src\Battle\Domain\Observer;

use Src\Battle\Domain\Combatant;

class BattleSubject
{
    /** @var BattleObserver[] */
    private array $observers = [];

    public function attach(BattleObserver $observer): void
    {
        $this->observers[] = $observer;
    }

    public function notifyEndTurn(Combatant $combatant): void
    {
        foreach ($this->observers as $observer) {
            $observer->onEndTurn($combatant);
        }
    }

    public function notifyDamaged(Combatant $target, float $daño): void
    {
        foreach ($this->observers as $observer) {
            $observer->onDamaged($target, $daño);
        }
    }

    public function notifyHealed(Combatant $target, float $cantidad): void
    {
        foreach ($this->observers as $observer) {
            $observer->onHealed($target, $cantidad);
        }
    }

    public function notifyFainted(Combatant $combatant): void
    {
        foreach ($this->observers as $observer) {
            $observer->onFainted($combatant);
        }
    }
}
