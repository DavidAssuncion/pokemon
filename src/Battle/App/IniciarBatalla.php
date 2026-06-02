<?php

namespace Src\Battle\App;

use Src\Battle\Domain\BattleTeam;
use Src\Battle\Domain\Combatant;
use Src\Battle\Domain\Position;
use Src\Battle\Domain\TurnBattleAggregate;
use Src\Pokemon\Domain\PokemonEntity;

class IniciarBatalla
{
    public function ejecutar(array $team1Data, array $team2Data): array
    {
        $team1 = $this->crearEquipo($team1Data, 'Equipo 1');
        $team2 = $this->crearEquipo($team2Data, 'Equipo 2');

        $battle = new TurnBattleAggregate($team1, $team2);

        return $battle->ejecutarBatalla();
    }

    private function crearEquipo(array $teamData, string $name): BattleTeam
    {
        $team = new BattleTeam($name);

        foreach ($teamData as $i => $member) {
            $pokemon = $member['pokemon']; // PokemonEntity
            $posicion = $member['posicion'] ?? Position::VANGUARDIA;
            $combatant = new Combatant($pokemon, $posicion);
            $team->addCombatant($combatant, $posicion);
        }

        return $team;
    }
}
