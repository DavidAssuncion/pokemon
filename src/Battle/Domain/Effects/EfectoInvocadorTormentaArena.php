<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\TipoClima;

/**
 * Al iniciar la batalla, activa la tormenta de arena.
 */
class EfectoInvocadorTormentaArena implements InterfazEfecto
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onBattleStart(Combatiente $portador, AgregadoBatalla $battle): void
    {
        $battle->setWeather(TipoClima::TORMENTA_ARENA);
        $battle->agregarLog('¡Tormenta de arena!');
    }
}
