<?php

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\Combatant;
use Src\Battle\Domain\Observer\BattleSubject;

interface EffectInterface
{
    public function aplicar(Combatant $portador, BattleSubject $subject): void;
    public function obtenerClave(): string;
    public function esUnico(): bool;
}
