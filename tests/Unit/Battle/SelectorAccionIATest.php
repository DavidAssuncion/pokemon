<?php

declare(strict_types=1);

namespace Tests\Unit\Battle;

use PHPUnit\Framework\TestCase;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\AI\SelectorAccionIA;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\Posicion;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Lógica de IA extraída de AgregadoBatalla: selección de objetivo y movimiento.
 */
class SelectorAccionIATest extends TestCase
{
    use ConstruyeCombatientes;

    private SelectorAccionIA $selector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->selector = new SelectorAccionIA();
    }

    public function test_elegir_mejor_movimiento_elige_mayor_efectividad_por_potencia(): void
    {
        // Contra defensor NORMAL:
        // - LUCHA (1.5 × 90) = 135
        // - PLANTA (1.0 × 100) = 100
        // Gana el de LUCHA aunque tenga menos potencia.
        $atacante = $this->combatiente(
            stats: ['atk' => 100, 'def' => 100],
            moves: [
                new MovimientoBatalla('Plancha', 90, TipoPokemon::LUCHA, \Src\Battle\Domain\Enums\CategoriaMovimiento::FISICO),
                new MovimientoBatalla('Hoja Afilada', 100, TipoPokemon::PLANTA, \Src\Battle\Domain\Enums\CategoriaMovimiento::FISICO),
            ],
            tipos: [TipoPokemon::LUCHA],
            id: 'a1',
            nombre: 'Atacante',
        );
        $defensor = $this->combatiente(
            tipos: [TipoPokemon::NORMAL],
            id: 'd1',
            nombre: 'Defensor',
        );

        $mejor = $this->selector->elegirMejorMovimiento($atacante, $defensor);

        $this->assertInstanceOf(MovimientoBatalla::class, $mejor);
        $this->assertSame('Plancha', $mejor->nombre);
    }

    public function test_elegir_mejor_movimiento_sin_movimientos_devuelve_placaje(): void
    {
        $atacante = $this->combatiente(
            moves: [],
            tipos: [TipoPokemon::PLANTA],
            id: 'a1',
            nombre: 'Sin Movs',
        );
        $defensor = $this->combatiente(
            tipos: [TipoPokemon::AGUA],
            id: 'd1',
            nombre: 'Defensor',
        );

        $mejor = $this->selector->elegirMejorMovimiento($atacante, $defensor);

        $this->assertInstanceOf(MovimientoBatalla::class, $mejor);
        $this->assertSame('Placaje', $mejor->nombre);
        $this->assertSame(40, $mejor->potencia);
        $this->assertSame(TipoPokemon::NORMAL, $mejor->tipo);
    }

    public function test_elegir_objetivo_vanguardia_ataca_vanguardia_enemiga(): void
    {
        $actor = $this->combatiente(
            tipos: [TipoPokemon::PLANTA],
            id: 'a1',
            nombre: 'Actor',
            posicion: Posicion::VANGUARDIA,
        );
        $enemigoVanguardia = $this->combatiente(
            tipos: [TipoPokemon::AGUA],
            id: 'e1',
            nombre: 'Vanguardia',
            posicion: Posicion::VANGUARDIA,
        );
        $enemigoRetaguardia = $this->combatiente(
            tipos: [TipoPokemon::FUEGO],
            id: 'e2',
            nombre: 'Retaguardia',
            posicion: Posicion::RETAGUARDIA,
        );

        $battle = $this->batallaCon($actor, $enemigoVanguardia, $enemigoRetaguardia);

        $objetivo = $this->selector->elegirObjetivoPara($battle, $actor);

        $this->assertNotNull($objetivo);
        $this->assertTrue($objetivo->estaEnVanguardia());
        $this->assertSame('e1', $objetivo->id());
    }

    public function test_elegir_objetivo_vanguardia_sin_vanguardia_enemiga_ataca_retaguardia(): void
    {
        $actor = $this->combatiente(
            tipos: [TipoPokemon::PLANTA],
            id: 'a1',
            nombre: 'Actor',
            posicion: Posicion::VANGUARDIA,
        );
        $enemigoVanguardia = $this->combatiente(
            tipos: [TipoPokemon::AGUA],
            id: 'e1',
            nombre: 'Vanguardia',
            posicion: Posicion::VANGUARDIA,
        );
        $enemigoVanguardia->setHpActual(0);

        $enemigoRetaguardia = $this->combatiente(
            tipos: [TipoPokemon::FUEGO],
            id: 'e2',
            nombre: 'Retaguardia',
            posicion: Posicion::RETAGUARDIA,
        );

        $battle = $this->batallaCon($actor, $enemigoVanguardia, $enemigoRetaguardia);

        $objetivo = $this->selector->elegirObjetivoPara($battle, $actor);

        $this->assertNotNull($objetivo);
        $this->assertTrue($objetivo->estaEnRetaguardia());
        $this->assertSame('e2', $objetivo->id());
    }

    public function test_elegir_objetivo_retaguardia_elige_cualquier_enemigo_vivo(): void
    {
        $actor = $this->combatiente(
            tipos: [TipoPokemon::PLANTA],
            id: 'a1',
            nombre: 'Actor',
            posicion: Posicion::RETAGUARDIA,
        );
        $enemigoVanguardia = $this->combatiente(
            tipos: [TipoPokemon::AGUA],
            id: 'e1',
            nombre: 'Vanguardia',
            posicion: Posicion::VANGUARDIA,
        );
        $enemigoRetaguardia = $this->combatiente(
            tipos: [TipoPokemon::FUEGO],
            id: 'e2',
            nombre: 'Retaguardia',
            posicion: Posicion::RETAGUARDIA,
        );

        $battle = $this->batallaCon($actor, $enemigoVanguardia, $enemigoRetaguardia);

        $objetivo = $this->selector->elegirObjetivoPara($battle, $actor);

        $this->assertNotNull($objetivo);
        $this->assertContains($objetivo->id(), ['e1', 'e2']);
    }

    public function test_elegir_objetivo_todos_muertos_devuelve_null(): void
    {
        $actor = $this->combatiente(
            tipos: [TipoPokemon::PLANTA],
            id: 'a1',
            nombre: 'Actor',
            posicion: Posicion::VANGUARDIA,
        );
        $enemigoVanguardia = $this->combatiente(
            tipos: [TipoPokemon::AGUA],
            id: 'e1',
            nombre: 'Vanguardia',
            posicion: Posicion::VANGUARDIA,
        );
        $enemigoVanguardia->setHpActual(0);
        $enemigoRetaguardia = $this->combatiente(
            tipos: [TipoPokemon::FUEGO],
            id: 'e2',
            nombre: 'Retaguardia',
            posicion: Posicion::RETAGUARDIA,
        );
        $enemigoRetaguardia->setHpActual(0);

        $battle = $this->batallaCon($actor, $enemigoVanguardia, $enemigoRetaguardia);

        $objetivo = $this->selector->elegirObjetivoPara($battle, $actor);

        $this->assertNull($objetivo);
    }

    public function test_elegir_mejor_movimiento_empate_elige_el_primero(): void
    {
        // Ambos movimientos puntúan igual (PLANTA→NORMAL = 1.0 × 80 = 80).
        // El código usa `>` estricto → gana el primero (determinista).
        $atacante = $this->combatiente(
            stats: ['atk' => 100, 'def' => 100],
            moves: [
                new MovimientoBatalla('Hoja Afilada', 80, TipoPokemon::PLANTA, CategoriaMovimiento::FISICO),
                new MovimientoBatalla('Follaje', 80, TipoPokemon::PLANTA, CategoriaMovimiento::FISICO),
            ],
            tipos: [TipoPokemon::PLANTA],
            id: 'a1',
            nombre: 'Atacante',
        );
        $defensor = $this->combatiente(
            tipos: [TipoPokemon::NORMAL],
            id: 'd1',
            nombre: 'Defensor',
        );

        $mejor = $this->selector->elegirMejorMovimiento($atacante, $defensor);

        $this->assertSame('Hoja Afilada', $mejor->nombre);
    }

    private function batallaCon(Combatiente $actor, Combatiente $enemigo1, Combatiente $enemigo2): AgregadoBatalla
    {
        $team1 = new EquipoBatalla('T1');
        $team1->agregarCombatiente($actor, $actor->posicion());

        $team2 = new EquipoBatalla('T2');
        $team2->agregarCombatiente($enemigo1, $enemigo1->posicion());
        $team2->agregarCombatiente($enemigo2, $enemigo2->posicion());

        return new AgregadoBatalla($team1, $team2);
    }
}
