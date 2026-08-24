<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;

abstract class ManejadorDanioAbstracto implements ManejadorDanio
{
    private ?ManejadorDanio $next = null;

    public function setNext(ManejadorDanio $handler): ManejadorDanio
    {
        $this->next = $handler;

        return $handler;
    }

    public function handle(AccionBatalla $action, float $daño): float
    {
        $daño = $this->process($action, $daño);

        if ($this->next !== null) {
            return $this->next->handle($action, $daño);
        }

        return $daño;
    }

    abstract protected function process(AccionBatalla $action, float $daño): float;
}
