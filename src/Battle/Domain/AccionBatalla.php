<?php

namespace Src\Battle\Domain;

class AccionBatalla
{
    public function __construct(
        public readonly Combatiente $attacker,
        public readonly Combatiente $defender,
        public readonly MovimientoBatalla $move,
        public readonly Posicion $fromPosition,
        public readonly bool $defenderTeamHasVanguard = false,
        public readonly string $weather = 'none',
    ) {}
}
