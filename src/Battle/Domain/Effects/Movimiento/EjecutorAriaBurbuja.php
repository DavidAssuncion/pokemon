<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\EstadoPokemon;

/** Aria Burbuja: cura la quemadura del objetivo. */
class EjecutorAriaBurbuja extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::ARIA_BURBUJA;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        if ($accion->defender->estado() === EstadoPokemon::BURN) {
            $accion->defender->setEstado(EstadoPokemon::NONE);
            $accion->defender->setTurnosEstado(0);
            $battle->agregarLog("{$accion->defender->nombre()} se cura de la quemadura");
        }
    }
}
