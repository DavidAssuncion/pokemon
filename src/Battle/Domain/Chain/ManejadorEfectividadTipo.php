<?php

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;

class ManejadorEfectividadTipo extends ManejadorDanioAbstracto
{
    protected function process(AccionBatalla $action, float $daño): float
    {
        $efectividad = $action->move->tipo->effectiveness($action->defender->pokemon);

        return $daño * $efectividad;
    }
}
