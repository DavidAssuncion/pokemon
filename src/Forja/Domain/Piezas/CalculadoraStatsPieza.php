<?php

declare(strict_types=1);

namespace Src\Forja\Domain\Piezas;

use Src\Forja\Domain\Piezas\Enums\TipoPieza;
use Src\Pokemon\Domain\Stats\StatsValue;

/**
 * Calcula los bonos de una pieza a partir de los stats base de su especie.
 *
 * Devuelve un `StatsValue` con solo los componentes del tipo; el resto queda a
 * cero. El redondeo es `floor` en cada componente tras aplicar el factor de la
 * etapa.
 */
final class CalculadoraStatsPieza
{
    public function calcular(StatsValue $base, TipoPieza $tipo, int $etapa): StatsValue
    {
        $factor = ReglasPiezas::factorEtapa($etapa);

        return match ($tipo) {
            TipoPieza::ARMA => new StatsValue(
                hp: 0.0,
                attack: $this->componente($base, 'atk', $factor),
                defense: 0.0,
                spAtk: $this->componente($base, 'spAtk', $factor),
                spDef: 0.0,
                speed: 0.0,
            ),
            TipoPieza::ARMADURA => new StatsValue(
                hp: $this->componente($base, 'hp', $factor),
                attack: 0.0,
                defense: $this->componente($base, 'def', $factor),
                spAtk: 0.0,
                spDef: $this->componente($base, 'spDef', $factor),
                speed: 0.0,
            ),
            TipoPieza::AMULETO => new StatsValue(
                hp: 0.0,
                attack: 0.0,
                defense: 0.0,
                spAtk: 0.0,
                spDef: 0.0,
                speed: $this->componente($base, 'speed', $factor),
            )->conStat(
                $this->statMaximo($base),
                floor($this->valorBonusAmuleto($base) * ReglasPiezas::BONO_AMULETO * $factor),
            ),
        };
    }

    private function componente(StatsValue $base, string $stat, float $factor): float
    {
        return floor($base->valorDe($stat) * $factor);
    }

    /**
     * Stat de combate más alto de la especie, con desempate
     * atk → def → spAtk → spDef.
     */
    private function statMaximo(StatsValue $base): string
    {
        $prioridad = ReglasPiezas::prioridadStatMaximo();
        $elegido = 'atk';
        $maximo = PHP_INT_MIN;

        foreach ($prioridad as $stat) {
            $valor = (int) $base->valorDe($stat);

            if ($valor > $maximo) {
                $maximo = $valor;
                $elegido = $stat;
            }
        }

        return $elegido;
    }

    private function valorBonusAmuleto(StatsValue $base): float
    {
        return $base->valorDe($this->statMaximo($base));
    }
}
