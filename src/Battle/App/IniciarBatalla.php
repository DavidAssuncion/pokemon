<?php

declare(strict_types=1);

namespace Src\Battle\App;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\Posicion;
use Src\Battle\Presentation\DTOEquipoBatalla;
use Src\Pokemon\Domain\PokemonEntity;

class IniciarBatalla
{
    /**
     * @return string[]
     */
    public function ejecutar(DTOEquipoBatalla $team1Data, DTOEquipoBatalla $team2Data): array
    {
        $team1 = $this->crearEquipo($team1Data, 'Equipo 1');
        $team2 = $this->crearEquipo($team2Data, 'Equipo 2');

        $battle = new AgregadoBatalla($team1, $team2);

        return $battle->ejecutarBatalla();
    }

    private function crearEquipo(DTOEquipoBatalla $teamData, string $name): EquipoBatalla
    {
        $team = new EquipoBatalla($name);

        foreach ($teamData->miembros as $member) {
            $pokemon = $member['pokemon']; // PokemonEntity
            $posicion = $member['posicion'] ?? Posicion::VANGUARDIA;
            $combatant = new Combatiente($pokemon, $posicion);
            $team->agregarCombatiente($combatant, $posicion);
        }

        return $team;
    }
}
