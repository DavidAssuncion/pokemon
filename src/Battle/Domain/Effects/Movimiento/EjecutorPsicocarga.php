<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;

/** Psicocarga: copia los cambios de estadísticas del objetivo. */
class EjecutorPsicocarga extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::PSICOCARGA;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $accion->attacker->setMultiplicadores($accion->defender->multiplicadores());
        $battle->agregarLog("{$accion->attacker->nombre()} copia los cambios de {$accion->defender->nombre()}");
    }
}
