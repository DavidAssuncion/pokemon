<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\Enums\ClaveCondicion;

/**
 * Aguante: si el golpe fuera letal, deja al portador a 1 HP. Se aplica al
 * final de la cadena para que el límite tenga en cuenta STAB, críticos,
 * efectividad y bonificadores.
 */
class ManejadorAguante extends ManejadorDanioAbstracto
{
    protected function process(AccionBatalla $action, float $daño): float
    {
        if (! $action->defender->tieneCondicion(ClaveCondicion::AGUANTE)) {
            return $daño;
        }

        $hp = $action->defender->hpActual();

        if ($daño < $hp) {
            return $daño;
        }

        $action->defender->quitarCondicion(ClaveCondicion::AGUANTE);

        return max(0.0, $hp - 1.0);
    }
}
