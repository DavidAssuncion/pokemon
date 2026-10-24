<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\ReglasBatalla;
use Src\Shared\Tipos\TipoPokemon;

class ManejadorSTAB extends ManejadorDanioAbstracto
{
    protected function process(AccionBatalla $action, float $daño): float
    {
        $tipo = $action->attacker->tipoEfectivoMovimiento($action);
        $multiplicador = self::tieneStabDeTipo($action->attacker, $tipo)
            ? ReglasBatalla::BONUS_STAB
            : 1.0;

        return $daño * $multiplicador * $action->attacker->multiplicadorStab($action);
    }

    /**
     * True si el movimiento comparte tipo con alguno de los tipos del atacante.
     * Compartida con MovesPreviewPresenter para que preview y daño real coincidan.
     */
    public static function tieneStab(Combatiente $atacante, MovimientoBatalla $movimiento): bool
    {
        return self::tieneStabDeTipo($atacante, $movimiento->tipo);
    }

    public static function tieneStabDeTipo(Combatiente $atacante, TipoPokemon $tipo): bool
    {
        foreach ($atacante->tiposDefensivos() as $tipoAtacante) {
            if ($tipoAtacante === $tipo) {
                return true;
            }
        }

        return false;
    }
}
