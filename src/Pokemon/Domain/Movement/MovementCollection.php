<?php

declare(strict_types=1);

namespace Src\Pokemon\Domain\Movement;

use Src\Shared\Domain\Collection;

class MovementCollection extends Collection
{
    public string $type = MovementEntity::class;

    public function __construct()
    {
    }
}
