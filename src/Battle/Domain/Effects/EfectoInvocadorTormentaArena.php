<?php

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\AgregadoBatalla;

/**
 * Al iniciar la batalla, activa la tormenta de arena.
 */
class EfectoInvocadorTormentaArena implements InterfazEfecto
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
    ) {}

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onBattleStart(Combatiente $portador, AgregadoBatalla $battle): void
    {
        $battle->weather = 'sandstorm';
        $battle->log[] = '¡Tormenta de arena!';
    }
}
