<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Ejecucion;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;

/**
 * Calcula y aplica el recoil del movimiento sobre el atacante.
 */
final class AplicadorRecoil
{
    public function aplicar(AccionBatalla $accion, float $danoTotal, ?AgregadoBatalla $battle): float
    {
        $perfil = $accion->move->perfil;

        if (($perfil->recoilFraccion <= 0.0 && $perfil->recoilHpMaximoFraccion <= 0.0)
            || ! $accion->attacker->estaVivo()
            || $accion->attacker->esInmuneARecoil()) {
            return 0.0;
        }

        $recoil = $perfil->recoilHpMaximoFraccion > 0.0
            ? $accion->attacker->pokemon()->battleStats()->hp * $perfil->recoilHpMaximoFraccion
            : $danoTotal * $perfil->recoilFraccion;

        if ($recoil <= 0.0) {
            return 0.0;
        }

        $accion->attacker->recibirDaño($recoil, false, 1.0);
        $battle?->agregarLog("{$accion->attacker->nombre()} recibe ".round($recoil).' de recoil');

        return $recoil;
    }
}
