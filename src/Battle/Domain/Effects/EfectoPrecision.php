<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\Effects\Capacidades\ModificaPrecision;

/** Ojo Compuesto: mejora la precisión de los ataques. */
class EfectoPrecision implements InterfazEfecto, ModificaPrecision
{
    use ComportamientosPorDefecto;

    public function __construct(private readonly string $clave, private readonly float $factor)
    {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function multiplicadorPrecision(): float
    {
        return $this->factor;
    }
}
