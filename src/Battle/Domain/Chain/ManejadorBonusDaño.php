<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\MovimientoBatalla;

/**
 * Aplica el multiplicador de daño contextual (bonus por tipo de movimiento
 * y situación de batalla, distinto del bonus de atributo del explorador que
 * ya cubre ManejadorBonificadorDanio).
 *
 * El hook protegido bonusPorTipo devuelve 1.0 por defecto (sin bonus). Las
 * reglas concretas (ej. "Cargado" eléctrico ×1.15 cuando el atacante recibe
 * un impacto eléctrico) se modelarán encima de este hook; de momento
 * Combatiente no dispone del flag correspondiente.
 *
 * Se encadena tras ManejadorObjetosEquipados y antes de ManejadorBonificadorDanio:
 * los bonus contextales y de atributo son multiplicativos y conmutativos, y ambos
 * deben aplicarse al final de la cadena sin alterar STAB/crítico/posición/clima.
 */
class ManejadorBonusDaño extends ManejadorDanioAbstracto
{
    protected function process(AccionBatalla $action, float $daño): float
    {
        $bonus = $this->bonusPorTipo($action->move);

        if ($bonus !== 1.0 && $action->attacker->estaVivo()) {
            $daño *= $bonus;
        }

        return $daño;
    }

    /**
     * Multiplicador contextual según el tipo o características del movimiento.
     */
    protected function bonusPorTipo(MovimientoBatalla $move): float
    {
        return 1.0;
    }
}
