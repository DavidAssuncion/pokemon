<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

use Src\Pokemon\Domain\PokemonEntity;
use Src\Shared\Tipos\TipoPokemon;

/**
 * @deprecated Clase incompleta. La funcionalidad de batalla automática
 *             está implementada en AgregadoBatalla::ejecutarBatalla().
 *             Pendiente de eliminar en una limpieza futura.
 *             Ver Oleada 1 - B1.9 en RESUMEN_TAREA.md
 */
class BattleAggregate
{
    public function __construct(
        public mixed $weather = null,
        public PokemonEntity $pokemon,
        public PokemonEntity $pokemonRival,
    ) {
    }
    // Clase para el combate automatico

    public function obtenerDiferenciasStatsAtaqueDefensa(): void
    {
    }

    public function obtenerMejorMovimiento(): void
    {
    }

    public function elegirMejorMovimiento(PokemonEntity $attacker, PokemonEntity $defender): mixed
    {
        $best = null;
        $bestScore = 0;

        foreach ($attacker->tiposCollection() as $move) {
            $score = $this->bestMoveMultiplier(
                $move->type(),
                $attacker,
                $defender,
                $move->isSpecial()
            );

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $move;
            }
        }

        return $best;
    }

    public function bestMoveMultiplier(TipoPokemon $tipoMovimiento, PokemonEntity $attacker, PokemonEntity $defender, bool $isSpecial): float
    {
        $base = $tipoMovimiento->effectiveness($defender);

        $attackStat = $isSpecial
            ? $attacker->battleStats()->spAtk
            : $attacker->battleStats()->attack;

        $defenseStat = $isSpecial
            ? $defender->battleStats()->spDef
            : $defender->battleStats()->defense;

        return $base * ($attackStat / max($defenseStat, 1));
    }
}
