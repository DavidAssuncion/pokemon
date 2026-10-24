<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ModificaEstadistica;
use Src\Battle\Domain\Enums\StatClave;

/** Flaqueza: reduce Ataque y At. Especial por debajo del 50 % de HP. */
class EfectoEstadisticaVida implements InterfazEfecto, ModificaEstadistica
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly StatClave $stat,
        private readonly float $multiplicador = 0.5,
        private readonly float $umbral = 0.5,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function multiplicadorEstadistica(Combatiente $portador, StatClave $estadistica): float
    {
        if ($estadistica !== $this->stat) {
            return 1.0;
        }

        return $portador->hpActual() <= $portador->pokemon()->battleStats()->hp * $this->umbral
            ? $this->multiplicador
            : 1.0;
    }
}
