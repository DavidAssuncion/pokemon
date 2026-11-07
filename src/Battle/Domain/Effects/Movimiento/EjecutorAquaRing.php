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
        $accion->defender->reemplazarCondicion(new CondicionVolatil(
            clave: ClaveCondicion::AQUA_RING,
            turnos: 5,
            curacionFija: 0.0,
        ));
        $battle->agregarLog("{$accion->defender->nombre()} queda rodeado por Acua Aro");
    }
}
