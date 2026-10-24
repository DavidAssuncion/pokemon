<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\EstadoPokemon;

/** Descanso: duerme dos turnos y recupera todo el HP. */
class EjecutorDescanso extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::DESCANSO;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $accion->attacker->setEstado(EstadoPokemon::SLEEP);
        $accion->attacker->setTurnosEstado(2);
        $accion->attacker->curarHpCantidad($accion->attacker->pokemon()->battleStats()->hp);
        $battle->agregarLog("{$accion->attacker->nombre()} se duerme y recupera energía");
    }
}
