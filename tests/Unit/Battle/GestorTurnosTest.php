<?php

declare(strict_types=1);

namespace Tests\Unit\Battle;

use PHPUnit\Framework\TestCase;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\GestorTurnos;
use Src\Battle\Domain\Posicion;
use Src\Shared\Tipos\TipoPokemon;

/**
 * GestorTurnos: rondas, turnos por velocidad acumulada, alive checks.
 */
class GestorTurnosTest extends TestCase
{
    use ConstruyeCombatientes;

    private function combatienteConSpeed(string $id, float $speed): Combatiente
    {
        return $this->combatiente(
            stats: ['hp' => 100, 'speed' => $speed],
            tipos: [TipoPokemon::NORMAL],
            id: $id,
            nombre: 'C'.$id,
        );
    }

    private function gestorConVarios(): GestorTurnos
    {
        $team1 = new EquipoBatalla('T1');
        $team1->agregarCombatiente($this->combatienteConSpeed('a1', 100), Posicion::VANGUARDIA);
        $team1->agregarCombatiente($this->combatienteConSpeed('a2', 50), Posicion::VANGUARDIA);

        $team2 = new EquipoBatalla('T2');
        $team2->agregarCombatiente($this->combatienteConSpeed('b1', 80), Posicion::VANGUARDIA);

        return new GestorTurnos($team1, $team2);
    }

    public function test_start_new_round_incrementa_round(): void
    {
        $gestor = $this->gestorConVarios();
        $this->assertSame(0, $gestor->round());

        $gestor->startNewRound();
        $this->assertSame(1, $gestor->round());

        $gestor->startNewRound();
        $this->assertSame(2, $gestor->round());
    }

    public function test_start_new_round_acumula_velocidad_y_resetea_veces(): void
    {
        $gestor = $this->gestorConVarios();
        $a1 = $gestor->allCombatants()[0];
        $a2 = $gestor->allCombatants()[1];

        // Simular que ya había actuado
        $a1->setVecesActuadoEstaRonda(3);

        $gestor->startNewRound();

        // vecesActuado reseteado
        $this->assertSame(0, $a1->vecesActuadoEstaRonda());
        $this->assertSame(0, $a2->vecesActuadoEstaRonda());

        // velocidad acumulada = stat efectivo speed (2*base+5 = 2*100+5 = 205, 2*50+5 = 105)
        $this->assertSame((float) (2 * 100 + 5), $a1->velocidadAcumulada());
        $this->assertSame((float) (2 * 50 + 5), $a2->velocidadAcumulada());
    }

    public function test_get_next_actor_devuelve_el_de_mayor_velocidad(): void
    {
        $gestor = $this->gestorConVarios();
        $gestor->startNewRound();

        $actor = $gestor->getNextActor();
        $this->assertNotNull($actor);
        // a1 tiene speed 100 → acumula 205, a2 tiene 50 → 105, b1 tiene 80 → 165
        $this->assertSame('a1', $actor->id());
    }

    public function test_empate_devuelve_el_primero_team1(): void
    {
        $team1 = new EquipoBatalla('T1');
        $team1->agregarCombatiente($this->combatienteConSpeed('a1', 100), Posicion::VANGUARDIA);
        $team2 = new EquipoBatalla('T2');
        $team2->agregarCombatiente($this->combatienteConSpeed('b1', 100), Posicion::VANGUARDIA);

        $gestor = new GestorTurnos($team1, $team2);
        $gestor->startNewRound();

        $actor = $gestor->getNextActor();
        $this->assertNotNull($actor);
        // Ambos con misma speed → gana el primero (teamA = a1)
        $this->assertSame('a1', $actor->id());
    }

    public function test_consume_action_reduce_velocidad_e_incrementa_veces(): void
    {
        $gestor = $this->gestorConVarios();
        $gestor->startNewRound();

        $actor = $gestor->getNextActor();
        $this->assertNotNull($actor);
        $this->assertSame('a1', $actor->id());

        $velInicial = $actor->velocidadAcumulada(); // 205
        $gestor->consumeAction($actor);

        // menorVelocidadEntreVivos: min(205, 105, 165) = 105
        $this->assertSame($velInicial - 105, $actor->velocidadAcumulada());
        $this->assertSame(1, $actor->vecesActuadoEstaRonda());
    }

    public function test_both_teams_alive_true_con_ambos_vivos(): void
    {
        $gestor = $this->gestorConVarios();
        $this->assertTrue($gestor->bothTeamsAlive());
    }

    public function test_both_teams_alive_false_si_un_equipo_cae(): void
    {
        $gestor = $this->gestorConVarios();

        // Matar a todos los de team2 (T2)
        foreach ($gestor->allCombatants() as $c) {
            if ($c === $gestor->allCombatants()[0] || $c === $gestor->allCombatants()[1]) {
                continue; // team1
            }
            $c->setHpActual(0);
        }

        $this->assertFalse($gestor->bothTeamsAlive());
    }

    public function test_hay_alguno_con_accion_pendiente_false_sin_vivos(): void
    {
        $gestor = $this->gestorConVarios();

        foreach ($gestor->allCombatants() as $c) {
            $c->setHpActual(0);
        }

        $this->assertFalse($gestor->hayAlgunoConAccionPendiente());
    }

    public function test_combatientes_vivos_excluye_muertos(): void
    {
        $gestor = $this->gestorConVarios();
        $this->assertCount(3, $gestor->combatientesVivos());

        // Matar a a2
        $a2 = $gestor->allCombatants()[1];
        $a2->setHpActual(0);

        $vivos = $gestor->combatientesVivos();
        $this->assertCount(2, $vivos);
        $ids = array_map(fn ($c) => $c->id(), $vivos);
        $this->assertNotContains('a2', $ids);
    }

    public function test_menor_velocidad_entre_vivos_devuelve_0_sin_vivos(): void
    {
        $gestor = $this->gestorConVarios();

        foreach ($gestor->allCombatants() as $c) {
            $c->setHpActual(0);
        }

        $this->assertSame(0.0, $gestor->menorVelocidadEntreVivos());
    }

    public function test_get_next_actor_devuelve_null_sin_vivos(): void
    {
        $gestor = $this->gestorConVarios();

        foreach ($gestor->allCombatants() as $c) {
            $c->setHpActual(0);
        }

        $this->assertNull($gestor->getNextActor());
    }

    public function test_consume_action_reduce_por_1_cuando_menor_velocidad_es_0(): void
    {
        $gestor = $this->gestorConVarios();
        $a1 = $gestor->allCombatants()[0];
        $a1->setVelocidadAcumulada(50);

        // Matar a todos (incluido a1) → menorVelocidadEntreVivos = 0 → reduce por 1
        foreach ($gestor->allCombatants() as $c) {
            $c->setHpActual(0);
        }

        $gestor->consumeAction($a1);

        $this->assertSame((float) (50 - 1), $a1->velocidadAcumulada());
        $this->assertSame(1, $a1->vecesActuadoEstaRonda());
    }
}
