<?php

namespace Src\Pokemon\Domain;

use Src\Pokemon\Domain\Stats\BattleStats;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Shared\Tipos\TiposCollection;

class PokemonEntity
{
    public BattleStats $battleStats;

    public function __construct(
        public StatsValue $stats,
        public StatsValue $evs,
        public array $moves, //pasar a collection y entity
        public TiposCollection $tiposCollection, //pasar a collection y entity
        public $objetos = null,
        public $habilidad = null
    ) {
        $this->battleStats = new BattleStats($stats, $evs);
    }

    public function habilidades() {}
    public function objetos() {}
}
