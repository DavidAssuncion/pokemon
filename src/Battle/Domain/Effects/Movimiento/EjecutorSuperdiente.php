<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;

/**
 * Superdiente: reduce a la mitad el HP actual del objetivo.
 */
class EjecutorSuperdiente extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::SUPERDIENTE;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $dano = $accion->defender->hpActual() * 0.5;
        if ($dano <= 0.0) {
            return;
        }

        $accion->defender->recibirDaño($dano, false, 1.0);
        $battle->agregarLog("Superdiente reduce a la mitad los PS de {$accion->defender->nombre()}");
    }
}
