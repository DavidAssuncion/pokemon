<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;

/** Aguante: si el golpe es letal, deja al usuario a 1 HP. */
class EjecutorAguante extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::AGUANTE;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $accion->attacker->reemplazarCondicion(new CondicionVolatil(ClaveCondicion::AGUANTE, 1));
        $battle->agregarLog("{$accion->attacker->nombre()} se prepara para aguantar");
    }
}
