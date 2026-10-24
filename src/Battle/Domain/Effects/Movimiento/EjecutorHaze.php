<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\ValueObjects\MultiplicadoresStats;

/** Niebla: elimina los cambios de estadísticas de todos los combatientes. */
class EjecutorHaze extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::NIEBLA;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        foreach ($battle->turnManager()->allCombatants() as $combatiente) {
            $combatiente->setMultiplicadores(MultiplicadoresStats::vacia());
        }
        $battle->agregarLog('¡La niebla borra todos los cambios de estadísticas!');
    }
}
