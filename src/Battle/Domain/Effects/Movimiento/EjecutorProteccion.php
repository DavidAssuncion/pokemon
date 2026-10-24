<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;

/** Protección / Detección: anula los ataques recibidos durante la ronda. */
class EjecutorProteccion extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::PROTECCION;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $accion->attacker->reemplazarCondicion(new CondicionVolatil(ClaveCondicion::PROTEGIDO, 1));
        $battle->agregarLog("{$accion->attacker->nombre()} se protege");
    }
}
