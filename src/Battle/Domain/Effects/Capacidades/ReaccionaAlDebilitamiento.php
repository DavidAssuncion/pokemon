<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Capacidades;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;

/**
 * Habilidad que reacciona cuando su portador cae debilitado (Detonación).
 */
interface ReaccionaAlDebilitamiento
{
    public function alDebilitar(Combatiente $portador, AgregadoBatalla $battle): void;
}
