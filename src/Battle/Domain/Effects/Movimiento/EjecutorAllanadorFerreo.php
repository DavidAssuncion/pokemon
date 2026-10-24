<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\TipoTerreno;

/**
 * Allanador Férreo: solo funciona si hay terreno y lo elimina al golpear.
 */
class EjecutorAllanadorFerreo extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::ALLANADOR_FERREO;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        if ($battle->terreno() !== TipoTerreno::NONE) {
            $battle->setTerreno(TipoTerreno::NONE);
            $battle->agregarLog('¡El terreno quedó destruido!');
        }
    }
}
