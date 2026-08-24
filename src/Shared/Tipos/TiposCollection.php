<?php

declare(strict_types=1);

namespace Src\Shared\Tipos;

use Src\Shared\Domain\Collection;

class TiposCollection extends Collection
{
    public string $type = TipoPokemon::class;

    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }
}
