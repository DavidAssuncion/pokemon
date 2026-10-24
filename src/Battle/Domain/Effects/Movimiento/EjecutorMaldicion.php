<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;

/**
 * Maldición: el usuario sacrifica la mitad de su HP y maldice al objetivo.
 * La maldición hace 1/16 al final de cada ronda y persiste aunque el usuario
 * caiga debilitado.
 */
class EjecutorMaldicion extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::MALDICION;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $coste = max(1.0, $accion->attacker->pokemon()->battleStats()->hp * 0.5);
        $accion->attacker->recibirDaño($coste, true, 1.0);

        $accion->defender->agregarCondicion(new CondicionVolatil(
            clave: ClaveCondicion::MALDICION,
            origenId: $accion->attacker->id(),
            fraccionDano: 0.0625,
            vaDirectoAHp: true,
        ));

        $battle->agregarLog("{$accion->attacker->nombre()} sacrifica vida y maldice a {$accion->defender->nombre()}");
    }
}
