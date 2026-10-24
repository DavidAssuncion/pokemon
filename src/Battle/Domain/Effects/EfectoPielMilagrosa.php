<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\Effects\Capacidades\ModificaPrecisionEstado;

/** Piel Milagrosa: reduce la precisión de los movimientos de estado rivales. */
class EfectoPielMilagrosa implements InterfazEfecto, ModificaPrecisionEstado
{
    use ComportamientosPorDefecto;

    public function __construct(private readonly string $clave, private readonly float $factor = 0.75)
    {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function multiplicadorPrecisionEstado(): float
    {
        return $this->factor;
    }
}
