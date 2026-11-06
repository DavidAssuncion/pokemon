<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Ejecucion;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;

/**
 * Aplica la curación del movimiento sobre el propio atacante.
 */
final class AplicadorCuracion
{
    public function aplicar(AccionBatalla $accion, AgregadoBatalla $battle): void
    {
        $perfil = $accion->move->perfil;
        if ($perfil->curacionFraccion <= 0.0
            || $accion->attacker !== $accion->defender
            || ! $accion->attacker->estaVivo()) {
            return;
        }

        $maxHp = max(1.0, $accion->attacker->pokemon()->battleStats()->hp);
        $curado = $accion->attacker->curarHpCantidad($maxHp * $perfil->curacionFraccion);
        if ($curado > 0.0) {
            $battle->agregarLog("{$accion->attacker->nombre()} recupera ".round($curado).' PS');
        }
    }
}
