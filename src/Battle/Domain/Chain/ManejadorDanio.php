<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;

interface ManejadorDanio
{
    public function setNext(ManejadorDanio $handler): ManejadorDanio;

    public function handle(AccionBatalla $action, float $daño): float;
}
