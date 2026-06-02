<?php

namespace Src\Shared\Tipos;

use Src\Shared\Domain\Collection;
use Src\Shared\Tipos\TipoPokemon;

class TiposCollection extends Collection
{
    public string $type = TipoPokemon::class;
    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }
}
