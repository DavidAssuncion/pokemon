<?php

namespace Src\Team\Domain;

class TeamAggregate
{
    public function __construct(
        public readonly TeamSrv $teamSrv,
        //public readonly TeamParticipants $participants,
    ) {}
}
