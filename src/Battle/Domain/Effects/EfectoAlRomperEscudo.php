<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlEscudoRoto;
use Src\Battle\Domain\ValueObjects\ResultadoDanio;

/**
 * Recupera escudo propio al romper un escudo enemigo (Bromista).
 *
 * `$porcentaje` se expresa en tanto por uno sobre el escudo máximo.
 */
class EfectoAlRomperEscudo implements InterfazEfecto, ReaccionaAlEscudoRoto
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly float $porcentaje,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function alRomperEscudo(
        Combatiente $portador,
        Combatiente $atacante,
        ResultadoDanio $resultado,
        AgregadoBatalla $battle,
    ): void {
        if ($portador !== $atacante || ! $resultado->rompioEscudo) {
            return;
        }

        $portador->curarBarreras($this->porcentaje * 100);
        $battle->agregarLog("{$portador->nombre()} recupera escudo al romper el del rival");
    }
}
