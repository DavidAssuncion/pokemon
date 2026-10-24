<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;

/**
 * Marca/hunt: coloca MARCADO sobre el objetivo. La marca refresca su duración
 * y factor si ya existía; el origen queda registrado para trazabilidad.
 */
class EjecutorMarca extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::MARCA;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        if (! $accion->defender->estaVivo() || $accion->defender === $accion->attacker) {
            return;
        }

        $perfil = $accion->move->perfil;
        $turnos = $perfil->turnosMarcado > 0 ? $perfil->turnosMarcado : 3;
        $factor = max(1.0, $perfil->multiplicadorMarcado);

        $accion->defender->reemplazarCondicion(new CondicionVolatil(
            clave: ClaveCondicion::MARCADO,
            turnos: $turnos,
            origenId: $accion->attacker->id(),
            multiplicadorDanoRecibido: $factor,
        ));

        $battle->agregarLog(
            "{$accion->defender->nombre()} queda marcado (+".
            (int) round(($factor - 1.0) * 100).'% daño recibido)',
        );
    }
}
