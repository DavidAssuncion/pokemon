<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;

/**
 * Persecución: la potencia sube hasta el doble según la vida que le falte al
 * objetivo.
 */
class EjecutorPersecucion extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::PERSECUCION;
    }

    public function potenciaEfectiva(AccionBatalla $accion, AgregadoBatalla $battle): ?int
    {
        $maxHp = $accion->defender->pokemon()->battleStats()->hp;
        if ($maxHp <= 0.0) {
            return null;
        }

        $vidaFaltante = max(0.0, 1.0 - ($accion->defender->hpActual() / $maxHp));

        return (int) round($accion->move->potencia * (1.0 + $vidaFaltante));
    }
}
