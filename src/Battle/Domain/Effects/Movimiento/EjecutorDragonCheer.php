<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;

/** Bramido Dragón: mejora la probabilidad de crítico de los aliados. */
class EjecutorDragonCheer extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::DRAGON_CHEER;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $equipo = $battle->equipoDe($accion->attacker);
        foreach ($equipo?->combatientesCollection()->vivos() ?? [] as $aliado) {
            if ($aliado !== $accion->attacker) {
                $aliado->agregarCondicion(new CondicionVolatil(ClaveCondicion::CRITICO_MEJORADO, 5));
            }
        }
        $battle->agregarLog("¡{$accion->attacker->nombre()} anima a su equipo!");
    }
}
