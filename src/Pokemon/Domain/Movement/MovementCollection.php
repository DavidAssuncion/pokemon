<?php

namespace Src\Pokemon\Domain\Movement;

use Src\Pokemon\Domain\Movement\MovementEntity;
use Src\Shared\Domain\Collection;

class MovementCollection extends Collection
{

    public string $type = MovementEntity::class;
    public function __construct() {}
}
