<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ModificaEstadistica;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\StatClave;

/** Multiplica una estadística mientras el portador tenga un estado concreto. */
class EfectoMultiplicadorEstado implements InterfazEfecto, ModificaEstadistica
{
    use ComportamientosPorDefecto;

    /** @param list<EstadoPokemon> $estados */
    public function __construct(
        private readonly string $clave,
        private readonly StatClave $estadistica,
        private readonly float $factor,
        private readonly array $estados,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function multiplicadorEstadistica(Combatiente $portador, StatClave $estadistica): float
    {
        if ($estadistica !== $this->estadistica) {
            return 1.0;
        }

        return in_array($portador->estado(), $this->estados, true) ? $this->factor : 1.0;
    }
}
