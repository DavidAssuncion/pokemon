<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Ejecucion;

use Src\Battle\Domain\AccionBatalla;

/**
 * Calcula y aplica el drenaje del movimiento sobre el atacante.
 */
final class AplicadorDrenaje
{
    public function aplicar(AccionBatalla $accion, float $danoHpTotal): float
    {
        $perfil = $accion->move->perfil;

        if (! $accion->attacker->estaVivo()
            || $perfil->drenajeFraccion <= 0.0
            || $danoHpTotal <= 0.0) {
            return 0.0;
        }

        if ($accion->defender->tieneEfecto('lodo_liquido')) {
            $cantidad = $danoHpTotal * $perfil->drenajeFraccion;
            $accion->attacker->recibirDaño($cantidad, false, 1.0);

            return 0.0;
        }

        return $accion->attacker->curarConExcedente($danoHpTotal * $perfil->drenajeFraccion);
    }
}
