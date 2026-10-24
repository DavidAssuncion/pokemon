<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;

/**
 * Gas Reactivo: mientras el portador esté vivo, anula las habilidades del
 * resto de combatientes. Al caer, las restaura.
 */
class EfectoGasReactivo implements InterfazEfecto
{
    use ComportamientosPorDefecto;

    public function __construct(private readonly string $clave)
    {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onBattleStart(Combatiente $portador, AgregadoBatalla $battle): void
    {
        $battle->activarGasReactivo($portador);
    }
}
