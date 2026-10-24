<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\Effects\Capacidades\InmuneAClima;
use Src\Battle\Domain\Enums\TipoClima;

/**
 * Inmunidad al daño residual de un clima (Velo Arena).
 */
class EfectoInmuneClima implements InterfazEfecto, InmuneAClima
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly TipoClima $clima,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function esInmuneAClima(TipoClima $clima): bool
    {
        return $this->clima === $clima;
    }
}
