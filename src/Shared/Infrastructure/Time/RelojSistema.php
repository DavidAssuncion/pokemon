<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Time;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Src\Shared\Domain\Ports\Reloj;

/**
 * Adaptador de reloj basado en el reloj del sistema.
 *
 * Comparte la semántica de `RelojPredeterminado` (Carbon) para que ambos
 * respeten `Carbon::setTestNow()` / `travelTo()`.
 */
final class RelojSistema implements Reloj
{
    public function ahora(): DateTimeImmutable
    {
        return CarbonImmutable::now();
    }
}
