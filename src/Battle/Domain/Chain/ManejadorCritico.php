<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\ReglasBatalla;

class ManejadorCritico extends ManejadorDanioAbstracto
{
    protected function process(AccionBatalla $action, float $daño): float
    {
        if ($action->isPreview) {
            return $daño;
        }

        $critChance = ReglasBatalla::CHANCE_CRITICO;
        $critBonus = ReglasBatalla::BONUS_CRITICO;

        return mt_rand() / mt_getrandmax() < $critChance
            ? $daño * $critBonus
            : $daño;
    }
}
