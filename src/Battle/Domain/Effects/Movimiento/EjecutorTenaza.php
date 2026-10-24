<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;

/**
 * Tenaza: deja al objetivo atrapado 2-5 turnos y le inflige 1/8 de su HP
 * máximo como daño físico al final de cada ronda.
 */
class EjecutorTenaza extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::TENAZA;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $roll = mt_rand(1, 1000);
        $turnos = $roll <= 375 ? 2 : ($roll <= 750 ? 3 : ($roll <= 875 ? 4 : 5));

        $accion->defender->agregarCondicion(new CondicionVolatil(
            clave: ClaveCondicion::ATRAPADO,
            turnos: $turnos,
            origenId: $accion->attacker->id(),
            fraccionDano: 0.125,
            esFisico: true,
        ));

        $battle->agregarLog("{$accion->defender->nombre()} queda atrapado por Tenaza");
    }
}
