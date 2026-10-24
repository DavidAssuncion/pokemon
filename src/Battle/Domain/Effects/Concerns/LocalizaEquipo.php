<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Concerns;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\EquipoBatalla;

/**
 * Localiza el equipo de un combatiente dentro del agregado.
 */
trait LocalizaEquipo
{
    protected function equipoDe(AgregadoBatalla $battle, Combatiente $portador): ?EquipoBatalla
    {
        if ($battle->team1->findCombatant($portador) !== null) {
            return $battle->team1;
        }

        if ($battle->team2->findCombatant($portador) !== null) {
            return $battle->team2;
        }

        return null;
    }

    protected function equipoRivalDe(AgregadoBatalla $battle, Combatiente $portador): ?EquipoBatalla
    {
        $equipo = $this->equipoDe($battle, $portador);
        if ($equipo === null) {
            return null;
        }

        return $equipo === $battle->team1 ? $battle->team2 : $battle->team1;
    }
}
