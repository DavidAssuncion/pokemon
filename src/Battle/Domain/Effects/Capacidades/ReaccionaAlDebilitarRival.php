<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Capacidades;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;

interface ReaccionaAlDebilitarRival
{
    public function alDebilitarRival(Combatiente $portador, Combatiente $rival, AgregadoBatalla $battle): void;
}
