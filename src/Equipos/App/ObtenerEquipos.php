<?php

declare(strict_types=1);

namespace Src\Equipos\App;

use Src\Equipos\Domain\TeamRepositoryInterface;

class ObtenerEquipos
{
    public function __construct(
        private readonly TeamRepositoryInterface $teamRepository,
    ) {
    }

    /** @return array */
    public function run(): array
    {
        return $this->teamRepository->obtenerTodos();
    }
}
