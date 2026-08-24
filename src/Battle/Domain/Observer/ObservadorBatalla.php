<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Observer;

use Src\Battle\Domain\Combatiente;

interface ObservadorBatalla
{
    public function onEndTurn(Combatiente $combatant): void;

    public function onDamaged(Combatiente $target, float $daño): void;

    public function onFainted(Combatiente $combatant): void;
}
