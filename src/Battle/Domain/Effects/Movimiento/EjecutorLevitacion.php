<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;

/** Levitón: inmunidad temporal a Tierra. */
class EjecutorLevitacion extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::LEVITACION_TEMPORAL;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $accion->attacker->reemplazarCondicion(new CondicionVolatil(ClaveCondicion::LEVITACION, 5));
        $battle->agregarLog("{$accion->attacker->nombre()} empieza a levitar");
    }
}
