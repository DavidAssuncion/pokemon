<?php

namespace Src\Shared\Tipos;

/**
 * Servicio para consultar efectividades entre tipos.
 * Permite calcular el multiplicador total de un tipo de movimiento
 * contra un Pokémon que puede tener uno o dos tipos.
 */
class TypeEffectService
{
    /**
     * Calcula el multiplicador de efectividad total.
     *
     * @param TipoPokemon      $moveType      Tipo del movimiento
     * @param TipoPokemon      $defenderType1 Primer tipo del defensor
     * @param TipoPokemon|null $defenderType2 Segundo tipo del defensor (opcional)
     * @return float Multiplicador (0.0, 0.25, 0.5, 1.0, 2.0, 4.0...)
     */
    public function calculate(
        TipoPokemon $moveType,
        TipoPokemon $defenderType1,
        ?TipoPokemon $defenderType2 = null,
    ): float {
        $mult = TypeChart::getEffectiveness($moveType, $defenderType1);

        if ($defenderType2 !== null) {
            $mult *= TypeChart::getEffectiveness($moveType, $defenderType2);
        }

        return $mult;
    }

    /**
     * Devuelve la matriz completa 18×18 de efectividades.
     * @return array<int, array<int, float>>
     */
    public function getChart(): array
    {
        return TypeChart::getChart();
    }

    /**
     * Devuelve la efectividad de un tipo de ataque contra un tipo defensor concreto.
     */
    public function getEffectiveness(TipoPokemon $attackType, TipoPokemon $defenderType): float
    {
        return TypeChart::getEffectiveness($attackType, $defenderType);
    }
}
