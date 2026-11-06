<?php

declare(strict_types=1);

namespace App\Livewire\Combate\Soporte;

use App\Livewire\Combate;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\MovimientoBatalla;

/**
 * Aplica los campos de animación al componente e incrementa su tick.
 * No guarda estado: la animación vive en las propiedades públicas del
 * componente.
 */
final class GestorAnimacionBatalla
{
    public function __construct(
        private readonly EstadoAnimacionBatalla $estadoAnimacion,
    ) {
    }

    public function movimiento(
        Combate $componente,
        Combatiente $actor,
        Combatiente $target,
        MovimientoBatalla $move,
    ): void {
        $componente->animTick++;
        $animacion = $this->estadoAnimacion->paraMovimiento($actor, $target, $move);

        $componente->animAttackerId = $animacion->attackerId;
        $componente->animAttackerNombre = $animacion->attackerNombre;
        $componente->animMoveNombre = $animacion->moveNombre;
        $componente->animDefenderId = $animacion->defenderId;
        $componente->animDefenderNombre = $animacion->defenderNombre;
    }

    public function objeto(
        Combate $componente,
        Combatiente $actor,
        Combatiente $target,
        string $itemNombre,
    ): void {
        $componente->animTick++;
        $animacion = $this->estadoAnimacion->paraObjeto($actor, $target, $itemNombre);

        $componente->animAttackerId = $animacion->attackerId;
        $componente->animAttackerNombre = $animacion->attackerNombre;
        $componente->animMoveNombre = $animacion->moveNombre;
        $componente->animDefenderId = $animacion->defenderId;
        $componente->animDefenderNombre = $animacion->defenderNombre;
    }

    public function reiniciar(Combate $componente): void
    {
        $animacion = $this->estadoAnimacion->reiniciada();

        $componente->animAttackerId = $animacion->attackerId;
        $componente->animDefenderId = $animacion->defenderId;
        $componente->animAttackerNombre = $animacion->attackerNombre;
        $componente->animDefenderNombre = $animacion->defenderNombre;
        $componente->animMoveNombre = $animacion->moveNombre;
        $componente->selectedTargetTeam = null;
        $componente->selectedTargetIdx = null;
        $componente->selectedTargetRefId = '';
        $componente->objetoSeleccionado = '';
        $componente->objetivosItem = '';
    }
}
