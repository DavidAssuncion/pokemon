<?php

declare(strict_types=1);

namespace Src\Battle\Infrastructure;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\Battle\Domain\Effects\FabricaEfectos;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\FabricaBatallaInterface;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\Posicion;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Fábrica temporal de datos mock para pruebas de batalla.
 * Reemplazar con datos reales de BD cuando esté disponible.
 */
class FabricaBatallaMock implements FabricaBatallaInterface
{
    public function __construct(
        private readonly ?FabricaEfectos $fabricaEfectos = null,
    ) {
    }
    /** @return DatosPokemonBatalla[] */
    public function generateTeam1(): array
    {
        return [
            new DatosPokemonBatalla(
                id: 'player_1',
                nombre: 'Gengar',
                hp: 60,
                atk: 65,
                def: 60,
                spAtk: 130,
                spDef: 75,
                speed: 110,
                tipos: [TipoPokemon::FANTASMA, TipoPokemon::VENENO],
                posicion: Posicion::RETAGUARDIA,
                moves: [
                    new MovimientoBatalla('Bola Sombra', 80, TipoPokemon::FANTASMA, CategoriaMovimiento::ESPECIAL),
                    new MovimientoBatalla('Bomba Lodo', 90, TipoPokemon::VENENO, CategoriaMovimiento::ESPECIAL),
                    new MovimientoBatalla('Rayo', 90, TipoPokemon::ELECTRICO, CategoriaMovimiento::ESPECIAL),
                    new MovimientoBatalla('Pulso Umbrío', 80, TipoPokemon::SINIESTRO, CategoriaMovimiento::ESPECIAL),
                    new MovimientoBatalla('Tóxico', 0, TipoPokemon::VENENO, CategoriaMovimiento::ESPECIAL, EstadoPokemon::POISON),
                    new MovimientoBatalla('Fuego Fatuo', 0, TipoPokemon::FUEGO, CategoriaMovimiento::ESPECIAL, EstadoPokemon::BURN),
                ],
                effectKeys: ['armor_pierce'],
                item: 'life_orb',
                speciesId: 94,
            ),
            new DatosPokemonBatalla(
                id: 'player_2',
                nombre: 'Giratina',
                hp: 150,
                atk: 100,
                def: 120,
                spAtk: 100,
                spDef: 120,
                speed: 90,
                tipos: [TipoPokemon::DRAGON],
                posicion: Posicion::VANGUARDIA,
                moves: [
                    new MovimientoBatalla('Garra Umbría', 80, TipoPokemon::FANTASMA, CategoriaMovimiento::FISICO),
                    new MovimientoBatalla('Cometa Draco', 130, TipoPokemon::DRAGON, CategoriaMovimiento::ESPECIAL),
                    new MovimientoBatalla('Danza Espada', 0, TipoPokemon::NORMAL, CategoriaMovimiento::ESTADO, selfStatChanges: [['stat' => 'attack', 'stages' => 2]]),
                    new MovimientoBatalla('Tierra Viva', 90, TipoPokemon::TIERRA, CategoriaMovimiento::ESPECIAL),
                ],
                shiny: true,
                speciesId: 487,
            ),
            new DatosPokemonBatalla(
                id: 'player_3',
                nombre: 'Tyranitar',
                hp: 100,
                atk: 134,
                def: 110,
                spAtk: 95,
                spDef: 100,
                speed: 61,
                tipos: [TipoPokemon::ROCA, TipoPokemon::SINIESTRO],
                posicion: Posicion::VANGUARDIA,
                moves: [
                    new MovimientoBatalla('Roca Afilada', 100, TipoPokemon::ROCA, CategoriaMovimiento::FISICO),
                    new MovimientoBatalla('Triturar', 80, TipoPokemon::SINIESTRO, CategoriaMovimiento::FISICO),
                    new MovimientoBatalla('Terremoto', 100, TipoPokemon::TIERRA, CategoriaMovimiento::FISICO),
                    new MovimientoBatalla('Onda Trueno', 0, TipoPokemon::ELECTRICO, CategoriaMovimiento::ESTADO, statusEffect: EstadoPokemon::PARALYSIS),
                ],
                shiny: true,
                effectKeys: ['sandstorm_summoner'],
                item: 'leftovers',
                speciesId: 248,
            ),
        ];
    }

    /** @return DatosPokemonBatalla[] */
    public function generateTeam2(): array
    {
        return [
            new DatosPokemonBatalla(
                id: 'enemy_1',
                nombre: 'Aggron',
                hp: 70,
                atk: 110,
                def: 180,
                spAtk: 60,
                spDef: 60,
                speed: 50,
                tipos: [TipoPokemon::ACERO, TipoPokemon::ROCA],
                posicion: Posicion::VANGUARDIA,
                moves: [
                    new MovimientoBatalla('Cabeza de Hierro', 80, TipoPokemon::ACERO, CategoriaMovimiento::FISICO),
                    new MovimientoBatalla('Roca Afilada', 100, TipoPokemon::ROCA, CategoriaMovimiento::FISICO),
                    new MovimientoBatalla('Terremoto', 100, TipoPokemon::TIERRA, CategoriaMovimiento::FISICO),
                    new MovimientoBatalla('Defensa Férrea', 0, TipoPokemon::ACERO, CategoriaMovimiento::ESTADO, selfStatChanges: [['stat' => 'defense', 'stages' => 2]]),
                ],
                effectKeys: ['regen_def'],
                speciesId: 306,
            ),
            new DatosPokemonBatalla(
                id: 'enemy_2',
                nombre: 'Deoxys',
                hp: 50,
                atk: 70,
                def: 160,
                spAtk: 70,
                spDef: 160,
                speed: 90,
                tipos: [TipoPokemon::PSIQUICO],
                posicion: Posicion::VANGUARDIA,
                moves: [
                    new MovimientoBatalla('Psíquico', 90, TipoPokemon::PSIQUICO, CategoriaMovimiento::ESPECIAL),
                    new MovimientoBatalla('Rayo', 90, TipoPokemon::ELECTRICO, CategoriaMovimiento::ESPECIAL),
                    new MovimientoBatalla('Psicorrayo', 65, TipoPokemon::PSIQUICO, CategoriaMovimiento::ESPECIAL, EstadoPokemon::CONFUSION),
                    new MovimientoBatalla('Pulso Umbrío', 80, TipoPokemon::SINIESTRO, CategoriaMovimiento::ESPECIAL),
                ],
                iconName: 'deoxys-defense',
                effectKeys: ['niebla_summoner'],
                speciesId: 386,
                formSuffix: 'f35',
            ),
            new DatosPokemonBatalla(
                id: 'enemy_3',
                nombre: 'Mewtwo',
                hp: 106,
                atk: 110,
                def: 90,
                spAtk: 154,
                spDef: 90,
                speed: 130,
                tipos: [TipoPokemon::PSIQUICO],
                posicion: Posicion::RETAGUARDIA,
                moves: [
                    new MovimientoBatalla('Psíquico', 90, TipoPokemon::PSIQUICO, CategoriaMovimiento::ESPECIAL),
                    new MovimientoBatalla('Esfera Aural', 80, TipoPokemon::LUCHA, CategoriaMovimiento::ESPECIAL),
                    new MovimientoBatalla('Llamarada', 110, TipoPokemon::FUEGO, CategoriaMovimiento::ESPECIAL),
                    new MovimientoBatalla('Paz Mental', 0, TipoPokemon::PSIQUICO, CategoriaMovimiento::ESTADO, selfStatChanges: [['stat' => 'spAtk', 'stages' => 1], ['stat' => 'spDef', 'stages' => 1]]),
                ],
                item: 'life_orb',
                speciesId: 150,
            ),
        ];
    }

    /**
     * Crea una batalla mock completa con ambos equipos.
     */
    public function createBattle(): AgregadoBatalla
    {
        $team1 = EquipoBatalla::fromData($this->generateTeam1(), 'Tú', $this->fabricaEfectos);
        $team2 = EquipoBatalla::fromData($this->generateTeam2(), 'Rival', $this->fabricaEfectos);

        $battle = new AgregadoBatalla($team1, $team2);
        $battle->triggerBattleStartEffects();

        return $battle;
    }

    public function crearEquiposMock(): EquipoBatalla
    {
        return EquipoBatalla::fromData($this->generateTeam1(), 'Mock', $this->fabricaEfectos);
    }
}
