<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;

/**
 * Homenaje Póstumo: 50 de potencia más 50 por cada aliado debilitado.
 */
class EjecutorHomenajePostumo extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::HOMENAJE_POSTUMO;
    }

    public function potenciaEfectiva(AccionBatalla $accion, AgregadoBatalla $battle): ?int
    {
        $equipo = $battle->equipoDe($accion->attacker);
        if ($equipo === null) {
            return null;
        }

        $debilitados = 0;
        foreach ($equipo->combatientesCollection()->all() as $aliado) {
            if ($aliado !== $accion->attacker && ! $aliado->estaVivo() && ! $aliado->esInvocacion()) {
                $debilitados++;
            }
        }

        return min(300, 50 + 50 * $debilitados);
    }
}
