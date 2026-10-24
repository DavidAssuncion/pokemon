<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\ReglasBatalla;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;

/**
 * Vínculo de Daño: los aliados vivos del usuario (excepto él) comparten una
 * fracción del daño que reciban con el usuario. El vínculo se refresca si ya
 * estaba activo y no se acumula.
 */
class EjecutorVinculoDano extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::VINCULO_DANO;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $equipo = $battle->equipoDe($accion->attacker);
        if ($equipo === null) {
            return;
        }

        $perfil = $accion->move->perfil;
        $turnos = $perfil->turnosVinculoDano > 0 ? $perfil->turnosVinculoDano : 4;
        $fraccion = $perfil->fraccionVinculoDano > 0.0
            ? min(ReglasBatalla::VINCULO_DANO_MAXIMO, $perfil->fraccionVinculoDano)
            : 0.3;

        $aplicados = 0;
        foreach ($equipo->combatientesCollection()->vivos() as $aliado) {
            if ($aliado === $accion->attacker) {
                continue;
            }

            $aliado->reemplazarCondicion(new CondicionVolatil(
                clave: ClaveCondicion::VINCULO_DANO,
                turnos: $turnos,
                origenId: $accion->attacker->id(),
                fraccionDanioCompartido: $fraccion,
                idVinculo: $accion->attacker->id(),
            ));
            $aplicados++;
        }

        if ($aplicados === 0) {
            $battle->agregarLog("{$accion->attacker->nombre()} no tiene aliados para vincular");

            return;
        }

        $battle->agregarLog(
            "{$accion->attacker->nombre()} vincula el daño de sus aliados (".
            (int) round($fraccion * 100).'% durante '.$turnos.' turnos)',
        );
    }
}
