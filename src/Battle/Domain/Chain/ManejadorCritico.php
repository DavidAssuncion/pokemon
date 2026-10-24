<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\Effects\Equipo\EfectoEquipoLuckyChant;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ReglasBatalla;
use Src\Battle\Domain\ValueObjects\CambioStat;

class ManejadorCritico extends ManejadorDanioAbstracto
{
    protected function process(AccionBatalla $action, float $daño): float
    {
        if ($action->isPreview || ! $action->move->perfil->puedeCriticar) {
            return $daño;
        }

        if (! $action->attacker->ignoraHabilidadRival() && $action->defender->esInmuneACritico()) {
            return $daño;
        }

        foreach ($action->equipoDefensor?->efectos()->all() ?? [] as $efecto) {
            if ($efecto instanceof EfectoEquipoLuckyChant && $efecto->estaActivo()) {
                return $daño;
            }
        }

        $bonusCritico = $action->attacker->tieneEfecto('francotirador')
            ? 2.25
            : ReglasBatalla::BONUS_CRITICO;

        if ($action->attacker->tieneEfecto('ensañamiento')
            && in_array($action->defender->estado(), [\Src\Battle\Domain\Enums\EstadoPokemon::POISON, \Src\Battle\Domain\Enums\EstadoPokemon::BAD_POISON], true)) {
            $this->aplicarPuntoIra($action);

            return $daño * $bonusCritico;
        }

        if ($action->move->perfil->criticoSeguro) {
            $this->aplicarPuntoIra($action);

            return $daño * $bonusCritico;
        }

        $critChance = min(
            1.0,
            ReglasBatalla::CHANCE_CRITICO
                * $action->move->perfil->ratioCritico
                * $action->attacker->multiplicadorProbabilidadCritico()
                * ($action->attacker->tieneCondicion(\Src\Battle\Domain\Enums\ClaveCondicion::CRITICO_MEJORADO) ? 2.0 : 1.0),
        );

        if (mt_rand() / mt_getrandmax() < $critChance) {
            $this->aplicarPuntoIra($action);

            return $daño * $bonusCritico;
        }

        return $daño;
    }

    private function aplicarPuntoIra(AccionBatalla $action): void
    {
        if (! $action->defender->tieneEfecto('punto_ira')) {
            return;
        }

        $cambio = CambioStat::desdeEtapas(StatClave::ATAQUE, 6);
        $action->defender->setMultiplicadores($cambio->aplicadoTemporalEn($action->defender->multiplicadores()));
    }
}
