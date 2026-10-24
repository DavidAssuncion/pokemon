<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\Enums\TipoTerreno;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Aplica el terreno activo al daño. Los terrenos potencian su tipo (×1.3) y
 * los movimientos marcados con `terrenoPotenciador` reciben además su factor
 * específico (Alto Voltaje, Vasta Fuerza, Psicohojas…).
 */
class ManejadorTerreno extends ManejadorDanioAbstracto
{
    protected function process(AccionBatalla $action, float $daño): float
    {
        $terreno = $action->terreno;
        if (! $terreno->esActivo()) {
            return $daño;
        }

        $tipo = $action->attacker->tipoEfectivoMovimiento($action);
        $multiplicador = $this->multiplicadorBase($terreno, $tipo);
        $perfil = $action->move->perfil;

        if ($perfil->terrenoPotenciador !== null && $perfil->terrenoPotenciador === $terreno) {
            $multiplicador *= $perfil->factorTerreno;
        }

        return $daño * $multiplicador;
    }

    private function multiplicadorBase(TipoTerreno $terreno, TipoPokemon $tipo): float
    {
        return match ($terreno) {
            TipoTerreno::HIERBA => $tipo === TipoPokemon::PLANTA ? 1.3 : 1.0,
            TipoTerreno::ELECTRICO => $tipo === TipoPokemon::ELECTRICO ? 1.3 : 1.0,
            TipoTerreno::PSIQUICO => $tipo === TipoPokemon::PSIQUICO ? 1.3 : 1.0,
            TipoTerreno::NIEBLA => $tipo === TipoPokemon::HADA ? 1.3 : 1.0,
            TipoTerreno::NONE => 1.0,
        };
    }
}
