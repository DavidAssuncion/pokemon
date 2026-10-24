<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Effects\Equipo\EfectoEquipoTailwind;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;

/**
 * Tailwind: duplica la velocidad del equipo durante 4 turnos.
 */
class EjecutorTailwind extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::TAILWIND;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $equipo = $battle->equipoDe($accion->attacker);
        if ($equipo === null) {
            return;
        }

        $equipo->agregarEfecto(new EfectoEquipoTailwind('tailwind'));
        $battle->agregarLog("{$accion->attacker->nombre()} activa Tailwind");
    }
}
