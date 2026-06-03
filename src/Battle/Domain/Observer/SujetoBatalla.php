<?php

namespace Src\Battle\Domain\Observer;

use Src\Battle\Domain\Combatiente;

class SujetoBatalla
{
    /** @var ObservadorBatalla[] */
    private array $observers = [];

    public function attach(ObservadorBatalla $observer): void
    {
        $this->observers[] = $observer;
    }

    public function notifyEndTurn(Combatiente $combatant): void
    {
        foreach ($this->observers as $observer) {
            $observer->onEndTurn($combatant);
        }
    }

    public function notifyDamaged(Combatiente $target, float $daño): void
    {
        foreach ($this->observers as $observer) {
            $observer->onDamaged($target, $daño);
        }
    }

    public function notifyFainted(Combatiente $combatant): void
    {
        foreach ($this->observers as $observer) {
            $observer->onFainted($combatant);
        }
    }
}
