<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;

/**
 * Orbe Vida: multiplica el daño final ×1.3 mientras el portador esté vivo.
 */
class ManejadorOrbeVida extends ManejadorDanioAbstracto
{
    protected function process(AccionBatalla $action, float $daño): float
    {
        if ($action->attacker->item() === 'life_orb' && $action->attacker->estaVivo()) {
            $daño *= 1.30;
        }

        return $daño;
    }
}
