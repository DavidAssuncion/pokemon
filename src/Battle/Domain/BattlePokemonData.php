<?php

namespace Src\Battle\Domain;

use Src\Shared\Tipos\TipoPokemon;

class BattlePokemonData
{
    /** @param BattleMove[] $moves */
    public function __construct(
        public readonly string $id,
        public readonly string $nombre,
        public readonly int $hp,
        public readonly int $atk,
        public readonly int $def,
        public readonly int $spAtk,
        public readonly int $spDef,
        public readonly int $speed,
        /** @var TipoPokemon[] */
        public readonly array $tipos,
        public readonly Position $posicion,
        public readonly array $moves,
        public readonly bool $shiny = false,
        public readonly string $iconName = '',
    ) {
        foreach ($moves as $m) {
            if (!$m instanceof BattleMove) {
                throw new \InvalidArgumentException('Moves must be BattleMove instances');
            }
        }
        foreach ($tipos as $t) {
            if (!$t instanceof TipoPokemon) {
                throw new \InvalidArgumentException('Types must be TipoPokemon instances');
            }
        }
    }
}
