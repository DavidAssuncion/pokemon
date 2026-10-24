<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Effects\Equipo\EfectoEquipoResistenciaTipo;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Chapoteo Lodo: reduce el daño eléctrico recibido por ambos equipos.
 */
class EjecutorMudSport extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::MUD_SPORT;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        foreach ([$battle->team1, $battle->team2] as $equipo) {
            $equipo->agregarEfecto(new EfectoEquipoResistenciaTipo(
                clave: ClaveEfectoMovimiento::MUD_SPORT->value,
                tipo: TipoPokemon::ELECTRICO,
                factor: 0.5,
                turnosRestantes: 5,
            ));
        }

        $battle->agregarLog('¡El campo se cubre de lodo y reduce el daño eléctrico!');
    }
}
