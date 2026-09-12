<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;

class CadenaDanio
{
    private ManejadorDanioAbstracto $first;

    public function __construct()
    {
        $manejadores = [
            new ManejadorDanioBase(),
            new ManejadorEfectividadTipo(),
            new ManejadorSTAB(),
            new ManejadorCritico(),
            new ManejadorPosicion(),
            new ManejadorClima(),
            new ManejadorObjetosEquipados(),
            new ManejadorBonusDaño(),
            new ManejadorBonificadorDanio(),
        ];

        for ($indice = 0; $indice < count($manejadores) - 1; $indice++) {
            $manejadores[$indice]->setNext($manejadores[$indice + 1]);
        }

        $this->first = $manejadores[0];
    }

    public function calculate(AccionBatalla $action): float
    {
        return max(1, floor($this->first->handle($action, 0)));
    }
}
