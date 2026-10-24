<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\Effects\Capacidades\InmuneARecoil;

/**
 * Cabeza Roca: no recibe daño de recoil.
 */
class EfectoInmuneRecoil implements InterfazEfecto, InmuneARecoil
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

    public function esInmuneARecoil(): bool
    {
        return true;
    }
}
