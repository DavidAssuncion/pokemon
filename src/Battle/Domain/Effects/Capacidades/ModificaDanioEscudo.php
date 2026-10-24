<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Capacidades;

use Src\Battle\Domain\AccionBatalla;

/**
 * Habilidad que incrementa el daño que el portador hace a las barreras.
 */
interface ModificaDanioEscudo
{
    public function multiplicadorDanioEscudo(AccionBatalla $accion): float;
}
