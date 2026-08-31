<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

use Src\Battle\Domain\Enums\TipoClima;

class AccionBatalla
{
    public function __construct(
        public readonly Combatiente $attacker,
        public readonly Combatiente $defender,
        public readonly MovimientoBatalla $move,
        public readonly Posicion $fromPosition,
        public readonly bool $defenderTeamHasVanguard = false,
        public readonly TipoClima $weather = TipoClima::NONE,
        public readonly bool $isPreview = false,
    ) {
    }
}
