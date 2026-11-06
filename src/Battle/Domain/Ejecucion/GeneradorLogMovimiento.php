<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Ejecucion;

use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\ReglasBatalla;

/**
 * Genera el mensaje de log descriptivo del movimiento ejecutado.
 */
final class GeneradorLogMovimiento
{
    public function generar(
        Combatiente $actor,
        Combatiente $objetivo,
        MovimientoBatalla $movimiento,
        float $daño,
        float $directPct,
        bool $defenderTeamHasVanguard,
    ): string {
        $dmgPart = $daño > 0 ? " → {$daño} de daño a {$objetivo->nombre()}" : '';
        $logMsg = "{$actor->nombre()} usa {$movimiento->nombre}{$dmgPart}";

        // El label del log está derivado de ReglasBatalla::MULTIPLICADOR_RETAGUARDIA
        // (reducción del 50% cuando el defensor está en retaguardia).
        if ($daño > 0 && $objetivo->estaEnRetaguardia() && $defenderTeamHasVanguard) {
            $logMsg .= ' (-'.(int) ((1 - ReglasBatalla::MULTIPLICADOR_RETAGUARDIA) * 100).'% retaguardia)';
        }

        if ($daño > 0 && $directPct > 0) {
            $logMsg .= ' ['.($directPct * 100).'% directo]';
        }

        return $logMsg;
    }
}
