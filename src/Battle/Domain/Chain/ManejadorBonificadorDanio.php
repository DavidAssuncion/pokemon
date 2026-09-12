<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;

/**
 * Aplica el multiplicador de daño por capacidad de combate del explorador
 * en combates de exploración. El valor lo define CapacidadesStats::bonusDanoCombate()
 * y se inyecta en Combatiente::setBonificadorDanio().
 *
 * Valor por defecto 1.0 (sin bonus). Se encadena justo después de
 * ManejadorObjetosEquipados para que sea el último multiplicador.
 */
class ManejadorBonificadorDanio extends ManejadorDanioAbstracto
{
    protected function process(AccionBatalla $action, float $daño): float
    {
        $bonificador = $action->attacker->bonificadorDanio();

        if ($bonificador !== 1.0 && $action->attacker->estaVivo()) {
            $daño *= $bonificador;
        }

        return $daño;
    }
}
