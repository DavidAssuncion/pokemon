<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;

/**
 * Represión Metal: devuelve el 150 % del último daño recibido por el usuario.
 */
class EjecutorRepresionMetal extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::REPRESION_METAL;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $ultimo = $accion->attacker->ultimoDanoRecibido();
        if ($ultimo <= 0.0) {
            return;
        }

        $devolucion = $ultimo * 1.5;
        $accion->defender->recibirDaño($devolucion, false, 1.0);
        $battle->agregarLog("{$accion->attacker->nombre()} devuelve ".round($devolucion).' de daño con Represión Metal');
    }
}
