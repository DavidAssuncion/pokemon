<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;
use Src\Shared\Domain\Sendas\PoliticaSenda;

/**
 * Aplica la reducción de daño recibido de la Senda del defensor para el tipo
 * efectivo del movimiento. El daño fijo no recibe Senda.
 */
class ManejadorSendaDefensiva extends ManejadorDanioAbstracto
{
    protected function process(AccionBatalla $action, float $daño): float
    {
        if ($action->move->perfil->formulaDano !== 'normal') {
            return $daño;
        }

        $tipo = $action->attacker->tipoEfectivoMovimiento($action);
        $factor = PoliticaSenda::factorDanoRecibido($action->defender->senda(), $tipo);

        return $factor === 1.0 ? $daño : $daño * $factor;
    }
}
