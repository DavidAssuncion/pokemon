<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Capacidades;

use Src\Battle\Domain\Enums\TipoClima;

/**
 * Habilidad que anula el daño residual de un clima concreto
 * (Velo Arena con tormenta de arena).
 */
interface InmuneAClima
{
    public function esInmuneAClima(TipoClima $clima): bool;
}
