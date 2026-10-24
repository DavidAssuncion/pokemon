<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Effects\Equipo\EfectoEquipoLuckyChant;
use Src\Battle\Domain\Effects\Equipo\EfectoEquipoMist;
use Src\Battle\Domain\Effects\Equipo\EfectoEquipoSafeguard;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;

/** Velo Sagrado, Neblina y Conjuro: protecciones de equipo. */
class EjecutorEquipoProteccion extends EjecutorMovimientoAbstracto
{
    public function __construct(private readonly ClaveEfectoMovimiento $claveEfecto)
    {
    }

    public function clave(): ClaveEfectoMovimiento
    {
        return $this->claveEfecto;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $equipo = $battle->equipoDe($accion->attacker);
        if ($equipo === null) {
            return;
        }

        $clave = $accion->move->perfil->claveEfecto;
        if ($clave === ClaveEfectoMovimiento::SAFEGUARD) {
            $equipo->agregarEfecto(new EfectoEquipoSafeguard('safeguard'));
            $battle->agregarLog('¡Velo Sagrado protege al equipo!');

            return;
        }

        if ($clave === ClaveEfectoMovimiento::MIST) {
            $equipo->agregarEfecto(new EfectoEquipoMist('mist'));
            $battle->agregarLog('¡Neblina protege las características del equipo!');

            return;
        }

        if ($clave === ClaveEfectoMovimiento::LUCKY_CHANT) {
            $equipo->agregarEfecto(new EfectoEquipoLuckyChant('lucky_chant'));
            $battle->agregarLog('¡Conjuro protege al equipo de los críticos!');
        }
    }
}
