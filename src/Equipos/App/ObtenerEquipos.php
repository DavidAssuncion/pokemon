<?php

namespace Src\Equipos\App;

use Illuminate\Database\Eloquent\Collection;
use Src\Equipos\Domain\TeamSrv;


class ObtenerEquipos
{
    public function __construct(
        public readonly TeamSrv $teamSrv,
    ) {}

    public function run(): Collection
    {
        return $this->teamSrv->obtenerTeams();
    }
}
