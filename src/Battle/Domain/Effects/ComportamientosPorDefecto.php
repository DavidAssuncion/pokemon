<?php

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Observer\SujetoBatalla;
use Src\Battle\Domain\AgregadoBatalla;

/**
 * Implementaciones vacías por defecto para todos los hooks de InterfazEfecto.
 * Los efectos concretos usan `use ComportamientosPorDefecto;` y sobrescriben solo
 * los métodos que necesiten.
 */
trait ComportamientosPorDefecto
{
    public function aplicar(Combatiente $portador, SujetoBatalla $subject): void {}

    public function esUnico(): bool
    {
        return true;
    }

    public function obtenerPorcentajeDanioDirecto(): float
    {
        return 0.0;
    }

    public function onBattleStart(Combatiente $portador, AgregadoBatalla $battle): void {}
    public function onRoundStart(Combatiente $portador, AgregadoBatalla $battle): void {}
    public function onRoundEnd(Combatiente $portador, AgregadoBatalla $battle): void {}
    public function onDamageDealt(Combatiente $portador, Combatiente $target, float $daño, AgregadoBatalla $battle): void {}
    public function onDamageReceived(Combatiente $portador, float $daño, AgregadoBatalla $battle): void {}
    public function onHealed(Combatiente $portador, float $cantidad): void {}
    public function onFainted(Combatiente $portador): void {}
    public function onTurnStart(Combatiente $portador, AgregadoBatalla $battle): void {}
    public function onTurnEnd(Combatiente $portador, AgregadoBatalla $battle): void {}
}
