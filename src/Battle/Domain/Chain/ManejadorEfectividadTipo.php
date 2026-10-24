<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;

class ManejadorEfectividadTipo extends ManejadorDanioAbstracto
{
    protected function process(AccionBatalla $action, float $daño): float
    {
        $tipo = $action->attacker->tipoEfectivoMovimiento($action);
        $efectividad = $action->defender->efectividadContra($tipo);

        if ($action->attacker->tieneEfecto('ojo_mental')
            && in_array($tipo, [\Src\Shared\Tipos\TipoPokemon::NORMAL, \Src\Shared\Tipos\TipoPokemon::LUCHA], true)
            && in_array(\Src\Shared\Tipos\TipoPokemon::FANTASMA, $action->defender->tiposDefensivos(), true)) {
            $efectividad = 1.0;
            foreach ($action->defender->tiposDefensivos() as $tipoDefensor) {
                if ($tipoDefensor !== \Src\Shared\Tipos\TipoPokemon::FANTASMA) {
                    $efectividad *= $tipo->effectivenessAgainst($tipoDefensor);
                }
            }
        }

        if ($action->attacker->tieneEfecto('valentia')
            && in_array($tipo, [\Src\Shared\Tipos\TipoPokemon::NORMAL, \Src\Shared\Tipos\TipoPokemon::LUCHA], true)
            && in_array(\Src\Shared\Tipos\TipoPokemon::FANTASMA, $action->defender->tiposDefensivos(), true)) {
            $efectividad = 1.0;
            foreach ($action->defender->tiposDefensivos() as $tipoDefensor) {
                if ($tipoDefensor !== \Src\Shared\Tipos\TipoPokemon::FANTASMA) {
                    $efectividad *= $tipo->effectivenessAgainst($tipoDefensor);
                }
            }
        }
        $perfil = $action->move->perfil;

        if ($perfil->tipoEfectividadEspecial !== null
            && $perfil->multiplicadorEfectividadEspecial !== 1.0) {
            foreach ($action->defender->tiposDefensivos() as $tipoDefensor) {
                if ($tipoDefensor === $perfil->tipoEfectividadEspecial) {
                    $efectividad *= $perfil->multiplicadorEfectividadEspecial;
                    break;
                }
            }
        }

        $daño *= $efectividad;

        if ($efectividad > 1.0 && $perfil->multiplicadorSiSupereficaz !== 1.0) {
            $daño *= $perfil->multiplicadorSiSupereficaz;
        }

        return $daño;
    }
}
