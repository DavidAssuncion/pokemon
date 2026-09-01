<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

use Src\Shared\Tipos\TipoPokemon;

class DatosPokemonBatalla
{
    /**
     * @param  MovimientoBatalla[]  $moves
     * @param  TipoPokemon[]  $tipos
     * @param  string[]  $effectKeys  Claves de efectos/habilidades (ej: 'armor_pierce')
     */
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
        public readonly Posicion $posicion,
        public readonly array $moves,
        public readonly bool $shiny = false,
        public readonly string $iconName = '',
        /** @var string[] */
        public readonly array $effectKeys = [],
        public readonly ?string $item = null,
        public readonly int $speciesId = 0,
        public readonly string $formSuffix = '',
        public readonly ?int $nivel = null,
    ) {
        foreach ($moves as $m) {
            if (! $m instanceof MovimientoBatalla) {
                throw new \InvalidArgumentException('Moves must be MovimientoBatalla instances');
            }
        }
        foreach ($tipos as $t) {
            if (! $t instanceof TipoPokemon) {
                throw new \InvalidArgumentException('Types must be TipoPokemon instances');
            }
        }
    }
}
