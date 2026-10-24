<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Capacidades;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;

/**
 * Habilidad que reacciona al recibir un movimiento de contacto.
 */
interface ReaccionaAlContacto
{
    public function alRecibirContacto(Combatiente $portador, Combatiente $atacante, AgregadoBatalla $battle): void;
}
