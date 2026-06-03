<?php

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Observer\SujetoBatalla;
use Src\Battle\Domain\AgregadoBatalla;

interface InterfazEfecto
{
    public function aplicar(Combatiente $portador, SujetoBatalla $subject): void;
    public function obtenerClave(): string;
    public function esUnico(): bool;

    /**
     * Porcentaje del daño infligido que ignora barreras y va directo a la salud.
     * Ej: 0.1 = 10% del daño penetra armaduras.
     */
    public function obtenerPorcentajeDanioDirecto(): float;

    // ─── Lifecycle hooks ─────────────────────────────────────

    public function onBattleStart(Combatiente $portador, AgregadoBatalla $battle): void;
    public function onRoundStart(Combatiente $portador, AgregadoBatalla $battle): void;
    public function onRoundEnd(Combatiente $portador, AgregadoBatalla $battle): void;

    // ─── Event hooks (per-combatant dispatch) ─────────────────

    public function onDamageDealt(Combatiente $portador, Combatiente $target, float $daño, AgregadoBatalla $battle): void;
    public function onDamageReceived(Combatiente $portador, float $daño, AgregadoBatalla $battle): void;
    public function onHealed(Combatiente $portador, float $cantidad): void;
    public function onFainted(Combatiente $portador): void;
    public function onTurnStart(Combatiente $portador, AgregadoBatalla $battle): void;
    public function onTurnEnd(Combatiente $portador, AgregadoBatalla $battle): void;
}
