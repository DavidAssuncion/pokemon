<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\Enums\ClaveCondicion;

/**
 * Protección y Aguante: Protección anula el daño del turno; Aguante deja al
 * portador a 1 HP si el golpe fuera letal.
 */
class ManejadorProteccion extends ManejadorDanioAbstracto
{
    protected function process(AccionBatalla $action, float $daño): float
    {
        if ($action->defender->tieneCondicion(ClaveCondicion::PROTEGIDO)) {
            return 0.0;
        }

        if ($action->defender->tieneCondicion(ClaveCondicion::AGUANTE)) {
            $hp = $action->defender->hpActual();
            if ($daño >= $hp) {
                $action->defender->quitarCondicion(ClaveCondicion::AGUANTE);

                return max(0.0, $hp - 1.0);
            }
        }

        return $daño;
    }
}
