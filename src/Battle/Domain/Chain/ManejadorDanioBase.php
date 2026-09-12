<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\Enums\StatClave;

class ManejadorDanioBase extends ManejadorDanioAbstracto
{
    protected function process(AccionBatalla $action, float $daño): float
    {
        $move = $action->move;
        $nivel = $action->attacker->pokemon()->battleStats()->nivel ?? 50;

        $atk = $move->esEspecial()
            ? $action->attacker->obtenerStatEfectivo(StatClave::ATAQUE_ESPECIAL)
            : $action->attacker->obtenerStatEfectivo(StatClave::ATAQUE);

        $def = $move->esEspecial()
            ? $action->defender->obtenerStatEfectivo(StatClave::DEFENSA_ESPECIAL)
            : $action->defender->obtenerStatEfectivo(StatClave::DEFENSA);

        return self::formulaBase($nivel, $move->potencia, $atk, $def);
    }

    /**
     * Fórmula base de daño (shared): ((2 × nivel / 5 + 2) × potencia × atk / def) / 50 + 2.
     * La reutilizan el manejador de la cadena y el auto-daño por confusión (nivel 50, potencia 40).
     */
    public static function formulaBase(int $nivel, int $potencia, float $atk, float $def): float
    {
        return (((2 * $nivel / 5 + 2) * $potencia * $atk / max($def, 1)) / 50) + 2;
    }
}
