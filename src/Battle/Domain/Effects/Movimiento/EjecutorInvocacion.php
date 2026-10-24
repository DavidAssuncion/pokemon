<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;

/**
 * Invocación/pet: añade una copia temporal del usuario al equipo. La copia
 * conserva apariencia y stats base, pero no estados ni efectos equipados.
 */
class EjecutorInvocacion extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::INVOCAR;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $perfil = $accion->move->perfil;

        $battle->invocarCombatiente(
            invocador: $accion->attacker,
            turnos: $perfil->turnosInvocacion > 0 ? $perfil->turnosInvocacion : 3,
            fraccionHp: $perfil->fraccionHpInvocacion > 0.0 ? $perfil->fraccionHpInvocacion : 0.35,
            maxInvocaciones: $perfil->maxInvocaciones,
            nombre: $perfil->nombreInvocacion,
        );
    }
}
