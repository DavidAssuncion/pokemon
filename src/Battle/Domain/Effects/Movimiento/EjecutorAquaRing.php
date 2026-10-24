<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;

/** Acua Aro: recupera 1/16 de HP al final de cada ronda. */
class EjecutorAquaRing extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::AQUA_RING;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $accion->attacker->reemplazarCondicion(new CondicionVolatil(ClaveCondicion::AQUA_RING, 0));
        $battle->agregarLog("{$accion->attacker->nombre()} queda rodeado por Acua Aro");
    }
}
