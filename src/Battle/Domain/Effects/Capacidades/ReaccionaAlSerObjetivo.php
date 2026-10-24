<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Capacidades;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;

/**
 * Habilidad que reacciona al ser elegida como objetivo de un movimiento
 * (Hypnotizador, Desafío).
 */
interface ReaccionaAlSerObjetivo
{
    public function alSerObjetivo(Combatiente $portador, AccionBatalla $accion, AgregadoBatalla $battle): void;
}
