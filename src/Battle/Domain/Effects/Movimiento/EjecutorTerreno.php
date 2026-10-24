<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\TipoTerreno;

/**
 * Establece un terreno durante cinco turnos.
 */
class EjecutorTerreno extends EjecutorMovimientoAbstracto
{
    public function __construct(
        private readonly ClaveEfectoMovimiento $claveEfecto,
        private readonly TipoTerreno $terreno,
    ) {
    }

    public function clave(): ClaveEfectoMovimiento
    {
        return $this->claveEfecto;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $battle->setTerreno($this->terreno, 5);
        $battle->agregarLog("{$accion->attacker->nombre()} activa {$this->terreno->label()}");
    }
}
