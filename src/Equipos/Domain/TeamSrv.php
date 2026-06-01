<?php

namespace Src\Equipos\Domain;

use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;

class TeamSrv
{
    public function __construct() {}

    public function obtenerTeams(): Collection
    {
        return Team::with('members.reclutado.pokemon')->get();
    }
}
