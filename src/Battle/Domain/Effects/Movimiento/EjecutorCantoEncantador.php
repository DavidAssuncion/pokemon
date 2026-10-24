<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;

/** Canto Encantador: confunde si el objetivo tiene boosts. */
class EjecutorCantoEncantador extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::CANTO_ENCANTADOR;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        if ($accion->defender->bonificacionesPositivas() <= 0) {
            return;
        }

        $accion->defender->agregarCondicion(new CondicionVolatil(
            clave: ClaveCondicion::CONFUSION,
            turnos: mt_rand(2, 4),
        ));
        $battle->agregarLog("¡{$accion->defender->nombre()} queda confundido!");
    }
}
