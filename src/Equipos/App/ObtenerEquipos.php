<?php

declare(strict_types=1);

namespace Src\Equipos\App;

use Src\Equipos\Domain\Collections\TeamCollection;
use Src\Equipos\Domain\TeamRepositoryInterface;

class ObtenerEquipos
{
    public function __construct(
        private readonly TeamRepositoryInterface $teamRepository,
    ) {
    }

    public function run(): TeamCollection
    {
        return $this->teamRepository->obtenerTodos();
    }
}
