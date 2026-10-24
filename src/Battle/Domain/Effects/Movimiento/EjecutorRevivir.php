<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;

/**
 * Bendición Revivir: devuelve a la vida al primer aliado debilitado con una
 * fracción de su HP máximo.
 */
class EjecutorRevivir extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::REVIVIR;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $equipo = $battle->equipoDe($accion->attacker);
        if ($equipo === null) {
            return;
        }

        foreach ($equipo->combatientesCollection()->all() as $aliado) {
            if ($aliado->estaVivo() || $aliado->esInvocacion()) {
                continue;
            }

            $fraccion = $accion->move->perfil->fraccionRevivir;
            if (! $aliado->revivir($fraccion)) {
                continue;
            }

            $battle->agregarLog(
                "¡{$aliado->nombre()} revive con ".round($fraccion * 100).'% de su HP!',
            );

            return;
        }

        $battle->agregarLog("{$accion->attacker->nombre()} no tiene aliados debilitados que revivir");
    }
}
