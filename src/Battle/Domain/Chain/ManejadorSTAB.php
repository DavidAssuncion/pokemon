<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\ReglasBatalla;

class ManejadorSTAB extends ManejadorDanioAbstracto
{
    protected function process(AccionBatalla $action, float $daño): float
    {
        return self::tieneStab($action->attacker, $action->move)
            ? $daño * ReglasBatalla::BONUS_STAB
            : $daño;
    }

    /**
     * True si el movimiento comparte tipo con alguno de los tipos del atacante.
     * Compartida con MovesPreviewPresenter para que preview y daño real coincidan.
     */
    public static function tieneStab(Combatiente $atacante, MovimientoBatalla $movimiento): bool
    {
        foreach ($atacante->pokemon()->tiposCollection() as $tipo) {
            if ($tipo === $movimiento->tipo) {
                return true;
            }
        }

        return false;
    }
}
