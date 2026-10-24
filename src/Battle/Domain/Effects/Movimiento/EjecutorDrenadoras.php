<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;

/**
 * Drenadoras: fija una planta al objetivo. Al final de cada ronda drena HP
 * (solo vida, no escudo) y cura al usuario. No se acumula.
 */
class EjecutorDrenadoras extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::DRENADORAS;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        if ($accion->defender->tieneCondicion(ClaveCondicion::DRENADORAS)) {
            return;
        }

        $accion->defender->agregarCondicion(new CondicionVolatil(
            clave: ClaveCondicion::DRENADORAS,
            origenId: $accion->attacker->id(),
            fraccionDano: 0.125,
            vaDirectoAHp: true,
        ));

        $battle->agregarLog("{$accion->defender->nombre()} queda atrapado por Drenadoras");
    }
}
