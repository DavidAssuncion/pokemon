<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\EstadoPokemon;

/** Purificación: cura el estado del objetivo y restaura PS al usuario. */
class EjecutorPurificar extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::PURIFICAR;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $accion->defender->setEstado(EstadoPokemon::NONE);
        $accion->defender->setTurnosEstado(0);
        $accion->defender->setContadorVenenoGrave(0);
        $cantidad = $accion->attacker->pokemon()->battleStats()->hp * 0.5;
        $accion->attacker->curarConExcedente($cantidad);
        $battle->agregarLog("{$accion->defender->nombre()} queda purificado y {$accion->attacker->nombre()} recupera PS");
    }
}
