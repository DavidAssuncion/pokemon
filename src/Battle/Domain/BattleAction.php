<?php

namespace Src\Battle\Domain;

class BattleAction
{
    public function __construct(
        public readonly Combatant $attacker,
        public readonly Combatant $defender,
        public readonly BattleMove $move,
        public readonly Position $fromPosition,
        public readonly bool $defenderTeamHasVanguard = false,
    ) {}
}
