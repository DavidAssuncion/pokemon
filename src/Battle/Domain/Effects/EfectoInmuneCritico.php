<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\Effects\Capacidades\InmuneCritico;

/**
 * Predicción: no recibe críticos.
 */
class EfectoInmuneCritico implements InterfazEfecto, InmuneCritico
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

    public function esInmuneACritico(): bool
    {
        return true;
    }
}
