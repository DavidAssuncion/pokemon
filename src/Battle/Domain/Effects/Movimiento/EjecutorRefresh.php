<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\EstadoPokemon;

/** Refresco: cura el estado alterado del usuario. */
class EjecutorRefresh extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::REFRESCO;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $accion->attacker->setEstado(EstadoPokemon::NONE);
        $accion->attacker->setTurnosEstado(0);
        $accion->attacker->setContadorVenenoGrave(0);
        $battle->agregarLog("{$accion->attacker->nombre()} se refresca y cura su estado");
    }
}
