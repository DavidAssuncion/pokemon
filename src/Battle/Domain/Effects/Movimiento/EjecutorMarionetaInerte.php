<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;

/**
 * Marioneta Inerte: reactiva temporalmente a un aliado derrotado (sin contar
 * invocaciones) y lo deja bajo control durante 3 turnos. Al expirar, vuelve a
 * quedar debilitado.
 */
class EjecutorMarionetaInerte extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::MARIONETA_INERTE;
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

            if (! $aliado->revivir(0.35)) {
                continue;
            }

            $aliado->reemplazarCondicion(new CondicionVolatil(
                clave: ClaveCondicion::MARIONETA_INERTE,
                turnos: 3,
                origenId: $accion->attacker->id(),
            ));

            $battle->agregarLog(
                "{$accion->attacker->nombre()} controla a {$aliado->nombre()} con Marioneta Inerte",
            );

            return;
        }

        $battle->agregarLog("{$accion->attacker->nombre()} no tiene aliados derrotados que controlar");
    }
}
