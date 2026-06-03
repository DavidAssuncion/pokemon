<?php

namespace Src\Battle\Infrastructure;

use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\Posicion;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Fábrica temporal de datos mock para pruebas de batalla.
 * Reemplazar con datos reales de BD cuando esté disponible.
 */
class FabricaBatallaMock
{
    /** @return DatosPokemonBatalla[] */
    public static function generateTeam1(): array
    {
        return [
            new DatosPokemonBatalla(
                id: 'player_1', nombre: 'Gengar',
                hp: 60, atk: 65, def: 60, spAtk: 130, spDef: 75, speed: 110,
                tipos: [TipoPokemon::FANTASMA, TipoPokemon::VENENO], posicion: Posicion::RETAGUARDIA,
                moves: [
                    new MovimientoBatalla('Bola Sombra', 80, TipoPokemon::FANTASMA, 'especial'),
                    new MovimientoBatalla('Bomba Lodo', 90, TipoPokemon::VENENO, 'especial'),
                    new MovimientoBatalla('Rayo', 90, TipoPokemon::ELECTRICO, 'especial'),
                    new MovimientoBatalla('Pulso Umbrío', 80, TipoPokemon::SINIESTRO, 'especial'),
                    new MovimientoBatalla('Tóxico', 0, TipoPokemon::VENENO, 'especial', 'poison'),
                    new MovimientoBatalla('Fuego Fatuo', 0, TipoPokemon::FUEGO, 'especial', 'burn'),
                ],
                effectKeys: ['armor_pierce'],
                item: 'life_orb',
            ),
            new DatosPokemonBatalla(
                id: 'player_2', nombre: 'Giratina',
                hp: 150, atk: 100, def: 120, spAtk: 100, spDef: 120, speed: 90,
                tipos: [TipoPokemon::DRAGON], posicion: Posicion::VANGUARDIA,
                moves: [
                    new MovimientoBatalla('Garra Umbría', 80, TipoPokemon::FANTASMA, 'fisico'),
                    new MovimientoBatalla('Cometa Draco', 130, TipoPokemon::DRAGON, 'especial'),
                    new MovimientoBatalla('Danza Espada', 0, TipoPokemon::NORMAL, 'estado', selfStatChanges: [['stat' => 'attack', 'stages' => 2]]),
                    new MovimientoBatalla('Tierra Viva', 90, TipoPokemon::TIERRA, 'especial'),
                ],
                shiny: true,
            ),
            new DatosPokemonBatalla(
                id: 'player_3', nombre: 'Tyranitar',
                hp: 100, atk: 134, def: 110, spAtk: 95, spDef: 100, speed: 61,
                tipos: [TipoPokemon::SINIESTRO], posicion: Posicion::VANGUARDIA,
                moves: [
                    new MovimientoBatalla('Roca Afilada', 100, TipoPokemon::ROCA, 'fisico'),
                    new MovimientoBatalla('Triturar', 80, TipoPokemon::SINIESTRO, 'fisico'),
                    new MovimientoBatalla('Terremoto', 100, TipoPokemon::TIERRA, 'fisico'),
                    new MovimientoBatalla('Onda Trueno', 0, TipoPokemon::ELECTRICO, 'estado', statusEffect: 'paralysis'),
                ],
                shiny: true,
                effectKeys: ['sandstorm_summoner'],
                item: 'leftovers',
            ),
        ];
    }

    /** @return DatosPokemonBatalla[] */
    public static function generateTeam2(): array
    {
        return [
            new DatosPokemonBatalla(
                id: 'enemy_1', nombre: 'Aggron',
                hp: 70, atk: 110, def: 180, spAtk: 60, spDef: 60, speed: 50,
                tipos: [TipoPokemon::ACERO, TipoPokemon::ROCA], posicion: Posicion::VANGUARDIA,
                moves: [
                    new MovimientoBatalla('Cabeza de Hierro', 80, TipoPokemon::ACERO, 'fisico'),
                    new MovimientoBatalla('Roca Afilada', 100, TipoPokemon::ROCA, 'fisico'),
                    new MovimientoBatalla('Terremoto', 100, TipoPokemon::TIERRA, 'fisico'),
                    new MovimientoBatalla('Defensa Férrea', 0, TipoPokemon::ACERO, 'estado', selfStatChanges: [['stat' => 'defense', 'stages' => 2]]),
                ],
                effectKeys: ['regen_def'],
            ),
            new DatosPokemonBatalla(
                id: 'enemy_2', nombre: 'Deoxys',
                hp: 50, atk: 70, def: 160, spAtk: 70, spDef: 160, speed: 90,
                tipos: [TipoPokemon::PSIQUICO], posicion: Posicion::VANGUARDIA,
                moves: [
                    new MovimientoBatalla('Psíquico', 90, TipoPokemon::PSIQUICO, 'especial'),
                    new MovimientoBatalla('Rayo', 90, TipoPokemon::ELECTRICO, 'especial'),
                    new MovimientoBatalla('Psicorrayo', 65, TipoPokemon::PSIQUICO, 'especial', 'confusion'),
                    new MovimientoBatalla('Pulso Umbrío', 80, TipoPokemon::SINIESTRO, 'especial'),
                ],
                iconName: 'deoxys-defense',
                effectKeys: ['niebla_summoner'],
            ),
            new DatosPokemonBatalla(
                id: 'enemy_3', nombre: 'Mewtwo',
                hp: 106, atk: 110, def: 90, spAtk: 154, spDef: 90, speed: 130,
                tipos: [TipoPokemon::PSIQUICO], posicion: Posicion::RETAGUARDIA,
                moves: [
                    new MovimientoBatalla('Psíquico', 90, TipoPokemon::PSIQUICO, 'especial'),
                    new MovimientoBatalla('Esfera Aural', 80, TipoPokemon::LUCHA, 'especial'),
                    new MovimientoBatalla('Llamarada', 110, TipoPokemon::FUEGO, 'especial'),
                    new MovimientoBatalla('Paz Mental', 0, TipoPokemon::PSIQUICO, 'estado', selfStatChanges: [['stat' => 'spAtk', 'stages' => 1], ['stat' => 'spDef', 'stages' => 1]]),
                ],
                item: 'life_orb',
            ),
        ];
    }

    /**
     * Crea una batalla mock completa con ambos equipos.
     */
    public static function createBattle(): AgregadoBatalla
    {
        $team1 = EquipoBatalla::fromData(self::generateTeam1(), 'Tú');
        $team2 = EquipoBatalla::fromData(self::generateTeam2(), 'Rival');

        $battle = new AgregadoBatalla($team1, $team2);
        $battle->triggerBattleStartEffects();

        return $battle;
    }
}
