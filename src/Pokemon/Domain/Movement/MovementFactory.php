<?php

declare(strict_types=1);

namespace Src\Pokemon\Domain\Movement;

use Src\Shared\Tipos\TiposCollection;

class MovementFactory
{
    public function __construct()
    {
    }

    public function paraTipo(TiposCollection $tipos): MovementCollection
    {
        return new MovementCollection();
    }
}
