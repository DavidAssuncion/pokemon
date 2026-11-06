<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Time;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Src\Shared\Domain\Ports\Reloj;

/**
 * Implementación predeterminada del puerto de tiempo.
 *
 * Devuelve el instante actual del sistema cuando no se inyecta un reloj fijo.
 * Usa Carbon para respetar `Carbon::setTestNow()` / `travelTo()` en pruebas.
 */
final class RelojPredeterminado implements Reloj
{
    /**
     * Instante actual.
     */
    public function ahora(): DateTimeImmutable
    {
        return CarbonImmutable::now();
    }
}
