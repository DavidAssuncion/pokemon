<?php

declare(strict_types=1);

namespace Tests\Unit\Battle;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\Posicion;
use Src\Pokemon\Domain\PokemonEntity;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Shared\Tipos\TiposCollection;

trait ConstruyeCombatientes
{
    /**
     * Construye un combatiente de prueba con stats y movimientos controlados.
     *
     * @param  array{hp?: int, atk?: int, def?: int, spAtk?: int, spDef?: int, speed?: int}  $stats
     * @param  MovimientoBatalla[]  $moves
     * @param  TipoPokemon[]  $tipos
     */
    private function combatiente(
        array $stats = [],
        array $moves = [],
        array $tipos = [],
        string $id = 'c1',
        string $nombre = 'Pokemon',
        ?string $item = null,
        Posicion $posicion = Posicion::VANGUARDIA,
    ): Combatiente {
        $pokemon = new PokemonEntity(
            stats: new StatsValue(
                hp: $stats['hp'] ?? 60,
                attack: $stats['atk'] ?? 100,
                defense: $stats['def'] ?? 100,
                spAtk: $stats['spAtk'] ?? 100,
                spDef: $stats['spDef'] ?? 100,
                speed: $stats['speed'] ?? 100,
            ),
            evs: new StatsValue(0, 0, 0, 0, 0, 0),
            moves: $moves,
            tiposCollection: new TiposCollection($tipos),
        );

        $combatant = new Combatiente($pokemon, $posicion);
        $combatant->setId($id);
        $combatant->setNombre($nombre);
        $combatant->setItem($item ?? '');

        return $combatant;
    }

    /**
     * Crea una batalla mínima con un combatiente por equipo.
     */
    private function batallaMinima(Combatiente $atacante, Combatiente $defensor): AgregadoBatalla
    {
        $team1 = new EquipoBatalla('T1');
        $team1->agregarCombatiente($atacante, $atacante->posicion());

        $team2 = new EquipoBatalla('T2');
        $team2->agregarCombatiente($defensor, $defensor->posicion());

        return new AgregadoBatalla($team1, $team2);
    }
}
