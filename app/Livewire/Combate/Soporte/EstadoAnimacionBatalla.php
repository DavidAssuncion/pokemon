<?php

declare(strict_types=1);

namespace App\Livewire\Combate\Soporte;

use App\Livewire\Combate\Soporte\DTO\AnimacionBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\MovimientoBatalla;

/**
 * Construye los campos de animación de un movimiento, un objeto o un reset.
 * Sin estado: el tick lo incrementa y lo posee el componente.
 */
final class EstadoAnimacionBatalla
{
    public function paraMovimiento(
        int $tickActual,
        Combatiente $actor,
        Combatiente $objetivo,
        MovimientoBatalla $movimiento,
    ): AnimacionBatalla {
        return $this->crear($tickActual, $actor, $objetivo, $movimiento->nombre);
    }

    public function paraObjeto(
        int $tickActual,
        Combatiente $actor,
        Combatiente $objetivo,
        string $itemNombre,
    ): AnimacionBatalla {
        return $this->crear($tickActual, $actor, $objetivo, $itemNombre);
    }

    public function reiniciada(int $tickActual): AnimacionBatalla
    {
        return new AnimacionBatalla(
            tick: $tickActual,
            attackerId: '',
            attackerNombre: '',
            defenderId: '',
            defenderNombre: '',
            moveNombre: '',
        );
    }

    private function crear(
        int $tickActual,
        Combatiente $actor,
        Combatiente $objetivo,
        string $moveNombre,
    ): AnimacionBatalla {
        // Los movimientos autodirigidos (ej: Danza Espada) no parpadean al defensor.
        $autodirigido = $objetivo->id() === $actor->id();

        return new AnimacionBatalla(
            tick: $tickActual,
            attackerId: $actor->id(),
            attackerNombre: $actor->nombre(),
            defenderId: $autodirigido ? '' : $objetivo->id(),
            defenderNombre: $autodirigido ? '' : $objetivo->nombre(),
            moveNombre: $moveNombre,
        );
    }
}
