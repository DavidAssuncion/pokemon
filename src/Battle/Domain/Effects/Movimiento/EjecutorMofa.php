<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Effects\Equipo\EfectoEquipoAromaVelo;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;

/** Mofa: bloquea los movimientos de estado del objetivo. */
class EjecutorMofa extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::MOFA;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        foreach ($battle->equipoDe($accion->defender)?->efectos()->all() ?? [] as $efecto) {
            if ($efecto instanceof EfectoEquipoAromaVelo && $efecto->bloqueaMofa()) {
                return;
            }
        }

        if ($accion->defender->tieneEfecto('despiste')) {
            return;
        }

        $accion->defender->agregarCondicion(new CondicionVolatil(
            clave: ClaveCondicion::MOFA,
            turnos: 3,
            origenId: $accion->attacker->id(),
        ));
        $battle->agregarLog("{$accion->defender->nombre()} no puede usar movimientos de estado");
    }
}
