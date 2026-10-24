<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\TipoTerreno;

/**
 * Activa un terreno al iniciar la batalla.
 */
class EfectoInvocadorTerreno implements InterfazEfecto
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly TipoTerreno $terreno,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onBattleStart(Combatiente $portador, AgregadoBatalla $battle): void
    {
        $battle->setTerreno($this->terreno, 5);
        $battle->agregarLog("¡{$this->terreno->label()} cubre el campo de batalla!");
    }
}
