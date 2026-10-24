<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;

/**
 * Aplica el multiplicador de racha del atacante. El combo se incrementa por
 * cada golpe que conecta y se rompe al fallar, usar un movimiento de estado,
 * quedar bloqueado o adoptar una postura defensiva.
 */
class ManejadorCombo extends ManejadorDanioAbstracto
{
    protected function process(AccionBatalla $action, float $daño): float
    {
        if (! $action->attacker->estaVivo()
            || $action->move->esEstado()
            || ! $action->move->perfil->potenciaCreciente
            || $action->move->perfil->formulaPotencia === 'combo_doble') {
            return $daño;
        }

        return $daño * $action->attacker->comboMultiplicadorPara($action->move);
    }
}
