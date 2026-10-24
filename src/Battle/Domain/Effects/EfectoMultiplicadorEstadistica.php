<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\Catalog\CatalogoObjetos;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ModificaEstadistica;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\Enums\TipoClima;

/**
 * Multiplicador de estadística condicionado por clima.
 * Cubre Clorofila (+25 % velocidad con sol), Ímpetu Arena (+25 % con tormenta)
 * y Velo Agua (+25 % con lluvia).
 */
class EfectoMultiplicadorEstadistica implements InterfazEfecto, ModificaEstadistica
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly StatClave $estadistica,
        private readonly float $multiplicador,
        private readonly ?TipoClima $clima = null,
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

        if ($this->clima !== null && $portador->clima() !== $this->clima) {
            return 1.0;
        }

        if (CatalogoObjetos::clavesDeEfecto()->contiene($this->clave)
            && ! $portador->itemActivo()) {
            return 1.0;
        }

        return $this->multiplicador;
    }
}
