<?php

declare(strict_types=1);

namespace Tests\Unit\Battle;

use PHPUnit\Framework\TestCase;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\Battle\Domain\Effects\FabricaEfectos;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\Posicion;
use Src\Shared\Tipos\TipoPokemon;

/**
 * EquipoBatalla: fromData (con efectos/items), vivos, vanguardia/retaguardia, búsquedas.
 */
class EquipoBatallaTest extends TestCase
{
    use ConstruyeCombatientes;

    private function datoPokemon(string $id): DatosPokemonBatalla
    {
        return new DatosPokemonBatalla(
            id: $id,
            nombre: 'P'.$id,
            hp: 100,
            atk: 100,
            def: 100,
            spAtk: 100,
            spDef: 100,
            speed: 100,
            tipos: [TipoPokemon::NORMAL],
            posicion: Posicion::VANGUARDIA,
            moves: [new MovimientoBatalla('Golpe', 50, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
        );
    }

    public function test_from_data_crea_combatientes_con_ids(): void
    {
        $equipo = EquipoBatalla::fromData([
            $this->datoPokemon('p1'),
            $this->datoPokemon('p2'),
            $this->datoPokemon('p3'),
        ], 'Equipo');

        $this->assertSame('Equipo', $equipo->name);
        $this->assertCount(3, $equipo->combatants());
        $this->assertSame('Pp1', $equipo->combatants()[0]->nombre());
    }

    public function test_from_data_procesa_efectos_y_items(): void
    {
        $fabrica = new FabricaEfectos();
        $fabrica->registrarEfecto('armor_pierce', \Src\Battle\Domain\Effects\EfectoPerforacionArmadura::class, 0.10);
        $fabrica->registrarItem('leftovers', \Src\Battle\Domain\Effects\EfectoRestos::class);

        $dato = new DatosPokemonBatalla(
            id: 'p1',
            nombre: 'Portador',
            hp: 100,
            atk: 100,
            def: 100,
            spAtk: 100,
            spDef: 100,
            speed: 100,
            tipos: [TipoPokemon::NORMAL],
            posicion: Posicion::VANGUARDIA,
            moves: [new MovimientoBatalla('Golpe', 50, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
            effectKeys: ['armor_pierce'],
            item: 'leftovers',
        );

        $equipo = EquipoBatalla::fromData([$dato], 'Equipo', $fabrica);

        $combatant = $equipo->combatants()[0];
        $this->assertSame('leftovers', $combatant->item());
        $this->assertTrue($combatant->tieneEfecto('armor_pierce'));
        $this->assertTrue($combatant->tieneEfecto('leftovers'));
    }

    public function test_combatientes_vivos_solo_vivos(): void
    {
        $equipo = new EquipoBatalla('Equipo');
        $c1 = $this->combatiente(tipos: [TipoPokemon::NORMAL], id: 'c1', nombre: 'Vivo');
        $c2 = $this->combatiente(tipos: [TipoPokemon::NORMAL], id: 'c2', nombre: 'Muerto');
        $c2->setHpActual(0);
        $equipo->agregarCombatiente($c1, Posicion::VANGUARDIA);
        $equipo->agregarCombatiente($c2, Posicion::VANGUARDIA);

        $this->assertCount(1, $equipo->combatientesVivos());
        $this->assertSame('Vivo', $equipo->combatientesVivos()[0]->nombre());
    }

    public function test_vanguardia_y_retaguardia_alive(): void
    {
        $equipo = new EquipoBatalla('Equipo');
        $van = $this->combatiente(tipos: [TipoPokemon::NORMAL], id: 'van', nombre: 'Vanguardia');
        $ret = $this->combatiente(tipos: [TipoPokemon::NORMAL], id: 'ret', nombre: 'Retaguardia', posicion: Posicion::RETAGUARDIA);
        $vanMuerto = $this->combatiente(tipos: [TipoPokemon::NORMAL], id: 'van2', nombre: 'VanMuerto');
        $vanMuerto->setHpActual(0);
        $equipo->agregarCombatiente($van, Posicion::VANGUARDIA);
        $equipo->agregarCombatiente($ret, Posicion::RETAGUARDIA);
        $equipo->agregarCombatiente($vanMuerto, Posicion::VANGUARDIA);

        $this->assertCount(1, $equipo->vanguardiaAlive());
        $this->assertCount(1, $equipo->retaguardiaAlive());
        $this->assertTrue($equipo->tieneVanguardiaViva());
    }

    public function test_sin_vanguardia_viva_devuelve_false(): void
    {
        $equipo = new EquipoBatalla('Equipo');
        $vanMuerto = $this->combatiente(tipos: [TipoPokemon::NORMAL], id: 'van', nombre: 'Van');
        $vanMuerto->setHpActual(0);
        $equipo->agregarCombatiente($vanMuerto, Posicion::VANGUARDIA);

        $this->assertFalse($equipo->tieneVanguardiaViva());
        $this->assertEmpty($equipo->vanguardiaAlive());
    }

    public function test_todos_debilitados(): void
    {
        $equipo = new EquipoBatalla('Equipo');
        $c1 = $this->combatiente(tipos: [TipoPokemon::NORMAL], id: 'c1', nombre: 'Muerto');
        $c1->setHpActual(0);
        $equipo->agregarCombatiente($c1, Posicion::VANGUARDIA);

        $this->assertTrue($equipo->todosDebilitados());

        $c2 = $this->combatiente(tipos: [TipoPokemon::NORMAL], id: 'c2', nombre: 'Vivo');
        $equipo->agregarCombatiente($c2, Posicion::VANGUARDIA);

        $this->assertFalse($equipo->todosDebilitados());
    }

    public function test_find_combatant_por_id(): void
    {
        $equipo = new EquipoBatalla('Equipo');
        $c1 = $this->combatiente(tipos: [TipoPokemon::NORMAL], id: 'abc', nombre: 'Buscado');
        $equipo->agregarCombatiente($c1, Posicion::VANGUARDIA);

        $this->assertSame($c1, $equipo->findCombatant($c1));
        $this->assertSame('Buscado', $equipo->findCombatantById('abc')?->nombre());
        $this->assertNull($equipo->findCombatantById('no_existe'));
    }

    public function test_lowest_speed_entre_vivos(): void
    {
        $equipo = new EquipoBatalla('Equipo');
        $rapido = $this->combatiente(
            stats: ['speed' => 150],
            tipos: [TipoPokemon::NORMAL],
            id: 'rapido',
            nombre: 'Rápido',
        );
        $lento = $this->combatiente(
            stats: ['speed' => 20],
            tipos: [TipoPokemon::NORMAL],
            id: 'lento',
            nombre: 'Lento',
        );
        $lento->setHpActual(0); // muerto → no cuenta
        $equipo->agregarCombatiente($rapido, Posicion::VANGUARDIA);
        $equipo->agregarCombatiente($lento, Posicion::VANGUARDIA);

        // speed stat lvl100 = 2*base+5
        $this->assertSame((float) (2 * 150 + 5), $equipo->lowestSpeed());
    }
}
