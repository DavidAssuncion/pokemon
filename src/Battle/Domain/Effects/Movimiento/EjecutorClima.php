<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\TipoClima;

/** Establece un clima durante cinco turnos. */
class EjecutorClima extends EjecutorMovimientoAbstracto
{
    public function __construct(
        private readonly ClaveEfectoMovimiento $claveEfecto,
        private readonly TipoClima $clima,
    ) {
    }

    public function clave(): ClaveEfectoMovimiento
    {
        return $this->claveEfecto;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $battle->setWeather($this->clima);
        $battle->agregarLog("{$accion->attacker->nombre()} cambia el clima a {$this->clima->label()}");
    }
}
