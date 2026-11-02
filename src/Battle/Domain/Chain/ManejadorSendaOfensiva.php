<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;
use Src\Shared\Domain\Sendas\PoliticaSenda;

/**
 * Aplica el bonus de daño infligido de la Senda del jugador al tipo efectivo
 * del movimiento. El daño fijo no recibe Senda.
 */
class ManejadorSendaOfensiva extends ManejadorDanioAbstracto
{
    protected function process(AccionBatalla $action, float $daño): float
    {
        if ($action->move->perfil->formulaDano !== 'normal') {
            return $daño;
        }

        $tipo = $action->attacker->tipoEfectivoMovimiento($action);
        $factor = PoliticaSenda::factorDanoInfligido($action->attacker->senda(), $tipo);

        return $factor === 1.0 ? $daño : $daño * $factor;
    }
}
