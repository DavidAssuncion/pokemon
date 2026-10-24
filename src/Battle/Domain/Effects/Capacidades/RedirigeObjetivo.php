<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Capacidades;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;

/**
 * Habilidad que fuerza que un ataque rival se dirija a su portador
 * (Colector, Pararrayos).
 */
interface RedirigeObjetivo
{
    public function redirige(AccionBatalla $accion): bool;

    public function alRedirigir(Combatiente $portador, AccionBatalla $accion, AgregadoBatalla $battle): void;
}
