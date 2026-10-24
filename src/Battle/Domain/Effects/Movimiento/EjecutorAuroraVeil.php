<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Effects\Equipo\EfectoEquipoAuroraVeil;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;

/**
 * Velo Aurora: pantalla que reduce daño físico y especial durante cinco turnos.
 */
class EjecutorAuroraVeil extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::PANTALLA_AURORA;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $equipo = $battle->equipoDe($accion->attacker);
        if ($equipo === null) {
            return;
        }

        foreach ($equipo->efectos()->all() as $efecto) {
            if ($efecto instanceof EfectoEquipoAuroraVeil) {
                return;
            }
        }

        $equipo->agregarEfecto(new EfectoEquipoAuroraVeil(
            clave: ClaveEfectoMovimiento::PANTALLA_AURORA->value,
            turnosRestantes: 5,
        ));
        $battle->agregarLog("{$accion->attacker->nombre()} levanta un Velo Aurora");
    }
}
