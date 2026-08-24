<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;

class CadenaDanio
{
    private ManejadorDanioAbstracto $first;

    public function __construct()
    {
        $base = new ManejadorDanioBase();
        $type = new ManejadorEfectividadTipo();
        $stab = new ManejadorSTAB();
        $crit = new ManejadorCritico();
        $position = new ManejadorPosicion();
        $weather = new ManejadorClima();
        $lifeOrb = new ManejadorOrbeVida();

        $base->setNext($type);
        $type->setNext($stab);
        $stab->setNext($crit);
        $crit->setNext($position);
        $position->setNext($weather);
        $weather->setNext($lifeOrb);

        $this->first = $base;
    }

    public function calculate(AccionBatalla $action): float
    {
        return max(1, floor($this->first->handle($action, 0)));
    }
}
