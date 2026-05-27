<?php

namespace Src\Battle\Domain;

use Src\Pokemon\Domain\PokemonEntity;
use Src\Shared\Tipos\TipoPokemon;

class BattleAggregate
{
    public function __construct(
        public readonly BattleSrv $battleSrv,
        //public $weather = null,
        public PokemonEntity $pokemon,
        public PokemonEntity $pokemonRival
    ) {
        //$chooseBestMove;
    }
    //Clase para el combate automatico
    //BattleSrv recogera la logica comun al automatico y la v2 manual
    //Por lo que pokemonentity debera ser compatible para ambos, tener la loica comun, y que este agregado sea quien orqueste o organiza las diferencais en las batallas


    public function obtenerDiferenciasStatsAtaqueDefensa() {}

    public function obtenerMejorMovimiento() {}
    public function chooseBestMove(PokemonEntity $attacker, PokemonEntity $defender)
    {
        $best = null;
        $bestScore = 0;

        foreach ($attacker->tiposCollection as $move) {
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
            ? $attacker->battleStats->spAtk
            : $attacker->battleStats->attack;

        $defenseStat = $isSpecial
            ? $defender->battleStats->spDef
            : $defender->battleStats->defense;

        return $base * ($attackStat / max($defenseStat, 1));
    }
}
