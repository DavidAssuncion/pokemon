<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\EstadoPokemon;

/** Aromaterapia / Campana Cura: cura los estados del equipo. */
class EjecutorAromaterapia extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::AROMATERAPIA;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $equipo = $battle->equipoDe($accion->attacker);
        if ($equipo === null) {
            return;
        }

        foreach ($equipo->combatientesCollection()->vivos() as $aliado) {
            $aliado->setEstado(EstadoPokemon::NONE);
            $aliado->setTurnosEstado(0);
            $aliado->setContadorVenenoGrave(0);
        }
        $battle->agregarLog('¡El equipo queda libre de estados alterados!');
    }
}
