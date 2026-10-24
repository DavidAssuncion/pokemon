<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\StatClave;

class ManejadorDanioBase extends ManejadorDanioAbstracto
{
    protected function process(AccionBatalla $action, float $daño): float
    {
        $move = $action->move;
        $perfil = $move->perfil;

        if (! $move->esEstado()
            && ($action->attacker->tieneEfecto('mutatipo') || $action->attacker->tieneEfecto('libero'))) {
            $action->attacker->aplicarTipoOverride($move->tipo, 0);
        }

        $nivel = $action->attacker->pokemon()->battleStats()->nivel ?? 50;

        if ($perfil->formulaDano !== 'normal') {
            return $this->danoFijo($action);
        }

        if ($perfil->danoPorcentajeHpObjetivo > 0.0 && $perfil->danoFijoPorPorcentaje) {
            $hpMax = max(1.0, $action->defender->pokemon()->battleStats()->hp);
            $dano = $hpMax * $perfil->danoPorcentajeHpObjetivo;

            return $dano;
        }

        if ($perfil->danoPorcentajeHpAtacante > 0.0 && $perfil->danoFijoPorPorcentaje) {
            $hpMax = max(1.0, $action->attacker->pokemon()->battleStats()->hp);
            $dano = $hpMax * $perfil->danoPorcentajeHpAtacante;

            return $dano;
        }

        $calculo = $perfil->calculoStats;
        if ($calculo === 'body_press') {
            $atk = $action->attacker->obtenerStatEfectivo(StatClave::DEFENSA);
        } elseif ($calculo === 'foul_play') {
            $atk = $action->defender->obtenerStatEfectivo(StatClave::ATAQUE);
        } else {
            $atk = $move->esEspecial()
                ? $action->attacker->obtenerStatEfectivo(StatClave::ATAQUE_ESPECIAL)
                : $action->attacker->obtenerStatEfectivo(StatClave::ATAQUE);
        }

        if ($calculo === 'psyshock') {
            $def = $action->defender->obtenerStatEfectivo(StatClave::DEFENSA);
        } else {
            $def = $move->esEspecial()
                ? $action->defender->obtenerStatEfectivo(StatClave::DEFENSA_ESPECIAL)
                : $action->defender->obtenerStatEfectivo(StatClave::DEFENSA);
        }

        if ($action->attacker->tieneEfecto('ignorante')) {
            $def = $calculo === 'psyshock'
                ? $action->defender->pokemon()->battleStats()->defense
                : ($move->esEspecial()
                    ? $action->defender->pokemon()->battleStats()->spDef
                    : $action->defender->pokemon()->battleStats()->defense);
        }

        if ($perfil->ignoraDefensas) {
            $def = 1.0;
        }

        $def *= $perfil->multiplicadorDefensaObjetivo;

        $potencia = $this->potenciaEfectiva($action);
        $factor = max(0.01, $perfil->factorPotencia);
        if ($perfil->potenciaMaxima > 0) {
            $potencia = min($perfil->potenciaMaxima, $potencia);
        }

        return self::formulaBase($nivel, (int) max(1, round($potencia * $factor)), $atk, $def);
    }

    private function danoFijo(AccionBatalla $action): float
    {
        return match ($action->move->perfil->formulaDano) {
            'hp_atacante' => max(1.0, $action->attacker->hpActual()),
            'mitad_hp_objetivo' => max(1.0, $action->defender->hpActual() * 0.5),
            'diferencia_hp' => max(0.0, $action->defender->hpActual() - $action->attacker->hpActual()),
            'nivel' => max(1.0, (float) ($action->attacker->pokemon()->battleStats()->nivel ?? 50)),
            'fijo_40' => 40.0,
            'fijo_20' => 20.0,
            'psiconda' => max(1.0, (float) (($action->attacker->pokemon()->battleStats()->nivel ?? 50) * (mt_rand(50, 150) / 100))),
            'ohko' => max(1.0, $action->defender->pokemon()->battleStats()->hp),
            default => 0.0,
        };
    }

    private function potenciaEfectiva(AccionBatalla $action): int
    {
        $perfil = $action->move->perfil;
        $potencia = $action->move->potencia;
        $atacante = $action->attacker;
        $defensor = $action->defender;
        $formula = $perfil->formulaPotencia;

        return match ($formula) {
            'hp_ratio' => (int) max(1, round(150 * (max(1.0, $atacante->hpActual())
                / max(1.0, $atacante->pokemon()->battleStats()->hp)))),
            'objetivo_hp' => (int) max(1, round(120 * (max(0.0, $defensor->hpActual())
                / max(1.0, $defensor->pokemon()->battleStats()->hp)))),
            'estado' => $defensor->estado()->value !== 'none' ? $potencia * 2 : $potencia,
            'veneno' => in_array($defensor->estado(), [EstadoPokemon::POISON, EstadoPokemon::BAD_POISON], true)
                ? $potencia * 2 : $potencia,
            'atacante_estado' => in_array($atacante->estado(), [
                EstadoPokemon::BURN,
                EstadoPokemon::POISON,
                EstadoPokemon::BAD_POISON,
                EstadoPokemon::PARALYSIS,
            ], true) ? $potencia * 2 : $potencia,
            'objetivo_vida_baja' => $defensor->hpActual() <= $defensor->pokemon()->battleStats()->hp * 0.5
                ? $potencia * 2 : $potencia,
            'objetivo_danado_ronda' => $defensor->danoRecibidoEstaRonda() > 0.0
                ? $potencia * 2 : $potencia,
            'objetivo_ya_actuo' => $defensor->vecesActuadoEstaRonda() > 0
                ? $potencia * 2 : $potencia,
            'objetivo_no_actuo' => $defensor->vecesActuadoEstaRonda() === 0
                ? $potencia * 2 : $potencia,
            'objetivo_con_boost' => $defensor->bonificacionesPositivas() > 0
                ? $potencia * 2 : $potencia,
            'atacante_danado_ronda' => $atacante->danoRecibidoEstaRonda() > 0.0
                ? $potencia * 2 : $potencia,
            'sin_objeto' => ! $atacante->itemActivo() ? $potencia * 2 : $potencia,
            'objetivo_boost' => $potencia + 20 * $defensor->bonificacionesPositivas(),
            'atacante_boost' => $potencia + 20 * $atacante->bonificacionesPositivas(),
            'golpes_recibidos' => min(350, $potencia + 50 * $atacante->golpesRecibidos()),
            'hp_inverso' => $this->potenciaPorHpInverso($atacante),
            'combo_doble' => (int) min(
                $perfil->potenciaMaxima > 0 ? $perfil->potenciaMaxima : 9999,
                $potencia * (2 ** $atacante->combo()),
            ),
            'velocidad_gyro' => (int) max(1, min(150, 25 * (
                $defensor->velocidadEfectiva() / max(1.0, $atacante->velocidadEfectiva())
            ))),
            'velocidad_electro' => $this->potenciaElectroball($atacante->velocidadEfectiva(), $defensor->velocidadEfectiva()),
            'peso' => $this->potenciaPorPeso($atacante->peso(), $defensor->peso()),
            'peso_objetivo' => $this->potenciaPorPesoObjetivo($defensor->peso()),
            'volatil' => ($defensor->tieneCondicion(ClaveCondicion::FLINCH)
                || $defensor->tieneCondicion(ClaveCondicion::CONFUSION)) ? $potencia * 2 : $potencia,
            default => $potencia,
        };
    }

    private function potenciaPorHpInverso(\Src\Battle\Domain\Combatiente $atacante): int
    {
        $ratio = max(0.0, $atacante->hpActual())
            / max(1.0, $atacante->pokemon()->battleStats()->hp);

        return match (true) {
            $ratio >= 0.6875 => 20,
            $ratio >= 0.3542 => 40,
            $ratio >= 0.2083 => 80,
            $ratio >= 0.1042 => 100,
            $ratio >= 0.0417 => 150,
            default => 200,
        };
    }

    private function potenciaElectroball(float $velocidadAtacante, float $velocidadDefensor): int
    {
        $ratio = $velocidadAtacante / max(1.0, $velocidadDefensor);

        return match (true) {
            $ratio >= 4.0 => 150,
            $ratio >= 3.0 => 120,
            $ratio >= 2.0 => 80,
            $ratio >= 1.0 => 60,
            default => 40,
        };
    }

    private function potenciaPorPeso(float $pesoAtacante, float $pesoDefensor): int
    {
        $ratio = max(0.1, $pesoAtacante) / max(0.1, $pesoDefensor);

        return match (true) {
            $ratio >= 5.0 => 120,
            $ratio >= 4.0 => 100,
            $ratio >= 3.0 => 80,
            $ratio >= 2.0 => 60,
            default => 40,
        };
    }

    private function potenciaPorPesoObjetivo(float $peso): int
    {
        return match (true) {
            $peso < 10.0 => 20,
            $peso < 25.0 => 40,
            $peso < 50.0 => 60,
            $peso < 100.0 => 80,
            $peso < 200.0 => 100,
            default => 120,
        };
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
