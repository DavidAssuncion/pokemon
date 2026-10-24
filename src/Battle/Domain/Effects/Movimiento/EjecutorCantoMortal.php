<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;

/**
 * Canto Mortal: el objetivo cae debilitado tras 3 rondas si no es cambiado.
 */
class EjecutorCantoMortal extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::CANTO_MORTAL;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $accion->defender->agregarCondicion(new CondicionVolatil(
            clave: ClaveCondicion::CANTO_MORTAL,
            turnos: 3,
            origenId: $accion->attacker->id(),
        ));

        $battle->agregarLog("{$accion->defender->nombre()} escucha el Canto Mortal");
    }
}
