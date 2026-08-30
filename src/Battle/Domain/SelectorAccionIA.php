<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Lógica de IA para batalla: selección de objetivo y de mejor movimiento.
 * Extraída de AgregadoBatalla para mantener el agregado orquestando solo el ciclo.
 */
class SelectorAccionIA
{
    public function elegirObjetivoPara(AgregadoBatalla $battle, Combatiente $actor): ?Combatiente
    {
        $enemigo = $battle->team1->findCombatant($actor) !== null
            ? $battle->team2
            : $battle->team1;

        if ($actor->estaEnVanguardia()) {
            $vanguardiaEnemiga = $enemigo->vanguardiaAlive();
            if (! empty($vanguardiaEnemiga)) {
                return $vanguardiaEnemiga[array_rand($vanguardiaEnemiga)];
            }

            $retaguardiaEnemiga = $enemigo->retaguardiaAlive();
            if (! empty($retaguardiaEnemiga)) {
                return $retaguardiaEnemiga[array_rand($retaguardiaEnemiga)];
            }
        }

        if ($actor->estaEnRetaguardia()) {
            $todosEnemigos = $enemigo->combatientesVivos();
            if (! empty($todosEnemigos)) {
                return $todosEnemigos[array_rand($todosEnemigos)];
            }
        }

        // Fallback: cualquier enemigo vivo
        $todosEnemigos = $enemigo->combatientesVivos();

        return ! empty($todosEnemigos) ? $todosEnemigos[array_rand($todosEnemigos)] : null;
    }

    public function elegirMejorMovimiento(Combatiente $attacker, Combatiente $defender): ?MovimientoBatalla
    {
        if ($attacker->pokemon()->moves()->isEmpty()) {
            return new MovimientoBatalla('Placaje', 40, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO);
        }

        $best = null;
        $bestScore = -1;

        foreach ($attacker->pokemon()->moves() as $move) {
            if ($move instanceof MovimientoBatalla) {
                $efectividad = $move->tipo->effectiveness($defender->pokemon());
                $score = $efectividad * $move->potencia;

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $move;
                }
            }
        }

        return $best;
    }
}
