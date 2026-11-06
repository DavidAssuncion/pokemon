<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Ejecucion;

use Src\Battle\Domain\AccionBatalla;

/**
 * Aplica el coste de velocidad del movimiento sobre el atacante.
 */
final class AplicadorCosteVelocidad
{
    public function aplicar(AccionBatalla $accion): void
    {
        $perfil = $accion->move->perfil;

        if (! $accion->attacker->estaVivo() || $perfil->costeVelocidadFraccion <= 0.0) {
            return;
        }

        $accion->attacker->aplicarCosteVelocidad($perfil->costeVelocidadFraccion);
    }
}
