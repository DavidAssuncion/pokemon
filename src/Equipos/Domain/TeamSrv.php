<?php

declare(strict_types=1);

namespace Src\Equipos\Domain;

class TeamSrv
{
    public function __construct(
        private readonly TeamRepositoryInterface $teamRepository,
    ) {
    }

    /** @return TeamAggregate[] */
    public function obtenerTeams(): array
    {
        return $this->teamRepository->obtenerTodos();
    }
}
