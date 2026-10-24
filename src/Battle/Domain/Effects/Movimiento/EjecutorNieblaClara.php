<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\ValueObjects\MultiplicadoresStats;

/** Niebla Clara: elimina los cambios de estadísticas del objetivo. */
class EjecutorNieblaClara extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::NIEBLA_CLARA;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $accion->defender->setMultiplicadores(MultiplicadoresStats::vacia());
        $battle->agregarLog("{$accion->defender->nombre()} pierde sus cambios de estadísticas");
    }
}
