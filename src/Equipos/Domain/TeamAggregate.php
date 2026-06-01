<?php

namespace Src\Equipos\Domain;

class TeamAggregate
{
    public function __construct(
        public readonly TeamSrv $teamSrv,
        //public readonly TeamParticipants $participants,
    ) {}
}
