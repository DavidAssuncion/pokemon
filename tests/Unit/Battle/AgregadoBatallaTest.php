<?php

declare(strict_types=1);

namespace Tests\Unit\Battle;

use PHPUnit\Framework\TestCase;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Infrastructure\FabricaBatallaMock;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Test de regresión para bugs 1 y 2: AgregadoBatalla::elegirMejorMovimiento
 * debe usar getters moves() en lugar de acceder a la propiedad privada $moves.
 */
class AgregadoBatallaTest extends TestCase
{
    use ConstruyeCombatientes;

    public function test_elegir_mejor_movimiento_accede_a_movimientos_por_getter(): void
    {
        $battle = (new FabricaBatallaMock())->createBattle();
        $attacker = $battle->team1->combatants()[0];
        $defender = $battle->team2->combatants()[0];

        $move = $battle->elegirMejorMovimiento($attacker, $defender);

        $this->assertInstanceOf(MovimientoBatalla::class, $move);
    }

    public function test_elegir_mejor_movimiento_con_sin_movimientos_devuelve_placaje(): void
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
            nombre: 'Def',
        );

        $battle = $this->batallaMinima($atacante, $defensor);

        $move = $battle->elegirMejorMovimiento($atacante, $defensor);

        $this->assertInstanceOf(MovimientoBatalla::class, $move);
        $this->assertSame('Placaje', $move->nombre);
    }
}
