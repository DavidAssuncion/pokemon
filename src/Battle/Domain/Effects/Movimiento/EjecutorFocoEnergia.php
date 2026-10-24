<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;

/** Foco Energía / Foco Láser: mejora la probabilidad de crítico. */
class EjecutorFocoEnergia extends EjecutorMovimientoAbstracto
{
    public function __construct(private readonly int $turnos = 5)
    {
    }

    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::FOCO_ENERGIA;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $accion->attacker->agregarCondicion(new CondicionVolatil(
            clave: ClaveCondicion::CRITICO_MEJORADO,
            turnos: $this->turnos,
        ));
        $battle->agregarLog("{$accion->attacker->nombre()} concentra su energía");
    }
}
