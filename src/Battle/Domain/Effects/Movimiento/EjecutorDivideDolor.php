<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;

/** Divide Dolor: reparte el HP actual entre usuario y objetivo. */
class EjecutorDivideDolor extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::DIVIDE_DOLOR_MOVIMIENTO;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $media = ($accion->attacker->hpActual() + $accion->defender->hpActual()) / 2;
        $accion->attacker->setHpActual($media);
        $accion->defender->setHpActual($media);
        $battle->agregarLog('¡Divide Dolor iguala los PS de ambos combatientes!');
    }
}
