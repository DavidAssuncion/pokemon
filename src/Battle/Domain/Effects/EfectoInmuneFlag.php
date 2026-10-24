<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\Effects\Capacidades\InmuneAFlag;
use Src\Battle\Domain\Enums\FlagMovimiento;

/** Inmunidad a movimientos con una flag concreta (sonido, bala...). */
class EfectoInmuneFlag implements InterfazEfecto, InmuneAFlag
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly FlagMovimiento $flag,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function esInmuneAFlag(FlagMovimiento $flag): bool
    {
        return $this->flag === $flag;
    }
}
