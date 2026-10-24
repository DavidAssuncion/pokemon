<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Danio;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\ReglasBatalla;
use Src\Battle\Domain\ValueObjects\PerfilMovimiento;

/**
 * Reparte una fracción del daño con el aliado vinculado.
 */
final class RepartidorVinculoDanio
{
    public function repartir(
        Combatiente $objetivo,
        MovimientoBatalla $movimiento,
        float $daño,
        float $directPct,
        PerfilMovimiento $perfil,
        AgregadoBatalla $battle,
        AccionBatalla $accion,
    ): float {
        $vinculo = $objetivo->condicion(ClaveCondicion::VINCULO_DANO);
        if ($vinculo === null) {
            return $daño;
        }

        $equipo = $battle->equipoDe($objetivo);
        $aliado = $equipo?->findCombatantById($vinculo->idVinculo);

        if ($aliado === null
            || $aliado === $objetivo
            || $aliado === $accion->attacker
            || ! $aliado->estaVivo()) {
            $objetivo->quitarCondicion(ClaveCondicion::VINCULO_DANO);

            return $daño;
        }

        $fraccion = max(0.0, min(ReglasBatalla::VINCULO_DANO_MAXIMO, $vinculo->fraccionDanioCompartido));
        if ($fraccion <= 0.0) {
            return $daño;
        }

        $compartido = $daño * $fraccion;
        if ($compartido <= 0.0) {
            return $daño;
        }

        $resultado = $aliado->recibirDaño(
            $compartido,
            $movimiento->esEspecial(),
            $directPct,
            $perfil->multiplicadorDanoEscudo * $accion->attacker->multiplicadorDanioEscudo($accion),
            $perfil->multiplicadorDanoSalud,
        );
        $aliado->registrarDanoRecibido($resultado->danoAplicado(), $accion->attacker->id());
        $battle->subject()->notifyDamaged($aliado, $resultado->danoAplicado());

        if ($resultado->danoAplicado() > 0.0) {
            $aliado->procesarDanoJefe($resultado->danoAplicado(), $battle);
        }
        $aliado->dispararDanioRecibido($resultado->danoAplicado(), $battle);
        $aliado->dispararDanioRecibidoCompleto(
            new AccionBatalla(
                attacker: $accion->attacker,
                defender: $aliado,
                move: $movimiento,
                defenderTeamHasVanguard: $accion->defenderTeamHasVanguard,
                weather: $accion->weather,
                isPreview: $accion->isPreview,
                equipoDefensor: $equipo,
                terreno: $accion->terreno,
            ),
            $resultado,
            $battle,
        );

        $battle->agregarLog(
            "{$objetivo->nombre()} comparte ".round($compartido)." de daño con {$aliado->nombre()}",
        );

        if (! $aliado->estaVivo()) {
            $battle->agregarLog("¡{$aliado->nombre()} se ha debilitado por el vínculo de daño!");
            $aliado->notificarDebilitamiento($battle);
        }

        return max(0.0, $daño - $compartido);
    }
}
