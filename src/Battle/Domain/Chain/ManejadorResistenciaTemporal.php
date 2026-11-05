<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;

/**
 * Aplica la resistencia temporal del defensor contra el tipo del movimiento.
 *
 * Vive en la cadena de daño propia (no en habilidades defensivas) para que
 * funcione también cuando el atacante ignora las habilidades del rival.
 */
class ManejadorResistenciaTemporal extends ManejadorDanioAbstracto
{
    protected function process(AccionBatalla $action, float $daño): float
    {
        if (! $action->defender->estaVivo()) {
            return $daño;
        }

        return $daño * $action->defender->multiplicadorResistenciaTemporal($action);
    }
}
