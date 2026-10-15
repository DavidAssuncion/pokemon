<?php

declare(strict_types=1);

namespace Tests\Unit\Battle;

use PHPUnit\Framework\TestCase;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\GestorTurnos;
use Src\Battle\Domain\Posicion;
use Src\Shared\Tipos\TipoPokemon;

/**
 * GestorTurnos: rondas, una acción por pokémon vivo y ronda en orden de
 * velocidad efectiva, alive checks.
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

    public function test_start_new_round_resetea_veces_actuado(): void
    {
        $gestor = $this->gestorConVarios();
        $a1 = $gestor->allCombatants()->all()[0];
        $a2 = $gestor->allCombatants()->all()[1];

        // Simular que ya había actuado
        $a1->setVecesActuadoEstaRonda(3);

        $gestor->startNewRound();

        // vecesActuado reseteado → cada vivo puede actuar una vez más
        $this->assertSame(0, $a1->vecesActuadoEstaRonda());
        $this->assertSame(0, $a2->vecesActuadoEstaRonda());
    }

    public function test_get_next_actor_devuelve_el_de_mayor_velocidad(): void
    {
        $gestor = $this->gestorConVarios();
        $gestor->startNewRound();

        $actor = $gestor->getNextActor();
        $this->assertNotNull($actor);
        // Velocidad efectiva: a1 (2*100+5 = 205) > b1 (165) > a2 (105)
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

    public function test_consume_action_marca_la_accion_y_pasa_al_siguiente(): void
    {
        $gestor = $this->gestorConVarios();
        $gestor->startNewRound();

        $actor = $gestor->getNextActor();
        $this->assertNotNull($actor);
        $this->assertSame('a1', $actor->id());

        $gestor->consumeAction($actor);

        $this->assertSame(1, $actor->vecesActuadoEstaRonda());

        // a1 ya actuó: el siguiente por velocidad efectiva es b1 (165 > 105 de a2)
        $siguiente = $gestor->getNextActor();
        $this->assertNotNull($siguiente);
        $this->assertSame('b1', $siguiente->id());
    }

    public function test_consume_action_marca_solo_al_actor_indicado(): void
    {
        $gestor = $this->gestorConVarios();
        $gestor->startNewRound();

        $a1 = $gestor->allCombatants()->all()[0];
        $gestor->consumeAction($a1);

        $this->assertSame(1, $a1->vecesActuadoEstaRonda());
        $this->assertSame(0, $gestor->allCombatants()->all()[1]->vecesActuadoEstaRonda());
        $this->assertSame(0, $gestor->allCombatants()->all()[2]->vecesActuadoEstaRonda());
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
            if ($c === $gestor->allCombatants()->all()[0] || $c === $gestor->allCombatants()->all()[1]) {
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
        $a2 = $gestor->allCombatants()->all()[1];
        $a2->setHpActual(0);

        $vivos = $gestor->combatientesVivos();
        $this->assertCount(2, $vivos);
        $ids = array_map(fn ($c) => $c->id(), $vivos->all());
        $this->assertNotContains('a2', $ids);
    }

    public function test_hay_alguno_con_accion_pendiente_true_con_vivos(): void
    {
        $gestor = $this->gestorConVarios();
        $gestor->startNewRound();

        $this->assertTrue($gestor->hayAlgunoConAccionPendiente());
    }

    public function test_hay_alguno_con_accion_pendiente_false_cuando_todos_actuaron(): void
    {
        $gestor = $this->gestorConVarios();
        $gestor->startNewRound();

        foreach ($gestor->combatientesVivos() as $c) {
            $gestor->consumeAction($c);
        }

        $this->assertFalse($gestor->hayAlgunoConAccionPendiente());
        $this->assertNull($gestor->getNextActor());
    }

    public function test_cada_vivo_actua_una_sola_vez_por_ronda(): void
    {
        $gestor = $this->gestorConVarios();
        $gestor->startNewRound();

        $orden = [];
        while (($actor = $gestor->getNextActor()) !== null) {
            $orden[] = $actor->id();
            $gestor->consumeAction($actor);
        }

        // Velocidad efectiva descendente: a1 (205) > b1 (165) > a2 (105)
        $this->assertSame(['a1', 'b1', 'a2'], $orden);
        $this->assertFalse($gestor->hayAlgunoConAccionPendiente());

        // Nueva ronda: vuelven a estar disponibles en el mismo orden
        $gestor->startNewRound();
        $this->assertSame('a1', $gestor->getNextActor()?->id());
    }

    public function test_invariante_next_actor_no_es_null_mientras_haya_pendientes(): void
    {
        $gestor = $this->gestorConVarios();
        $gestor->startNewRound();

        while ($gestor->hayAlgunoConAccionPendiente()) {
            $actor = $gestor->getNextActor();
            $this->assertNotNull($actor);
            $gestor->consumeAction($actor);
        }

        $this->assertNull($gestor->getNextActor());
    }

    public function test_velocidad_efectiva_no_positiva_no_cortocircuita_el_turno(): void
    {
        $team1 = new EquipoBatalla('T1');
        $team1->agregarCombatiente($this->combatienteConSpeed('a1', -10), Posicion::VANGUARDIA);

        $team2 = new EquipoBatalla('T2');
        $team2->agregarCombatiente($this->combatienteConSpeed('b1', -20), Posicion::VANGUARDIA);

        $gestor = new GestorTurnos($team1, $team2);
        $gestor->startNewRound();

        $this->assertTrue($gestor->hayAlgunoConAccionPendiente());

        $actor = $gestor->getNextActor();
        $this->assertNotNull($actor);
        // Efectivas: a1 = -15, b1 = -35 → actúa el menos lento
        $this->assertSame('a1', $actor->id());
    }

    public function test_paralisis_afecta_el_orden_de_actuacion(): void
    {
        $team1 = new EquipoBatalla('T1');
        $team1->agregarCombatiente($this->combatienteConSpeed('a1', 100), Posicion::VANGUARDIA);

        $paralizado = $this->combatienteConSpeed('b1', 100);
        $paralizado->setEstado(EstadoPokemon::PARALYSIS);

        $team2 = new EquipoBatalla('T2');
        $team2->agregarCombatiente($paralizado, Posicion::VANGUARDIA);

        $gestor = new GestorTurnos($team1, $team2);
        $gestor->startNewRound();

        // a1 efectiva 205; b1 paralizado 102.5 → actúa a1
        $this->assertSame('a1', $gestor->getNextActor()?->id());
    }

    public function test_get_next_actor_devuelve_null_sin_vivos(): void
    {
        $gestor = $this->gestorConVarios();

        foreach ($gestor->allCombatants() as $c) {
            $c->setHpActual(0);
        }

        $this->assertNull($gestor->getNextActor());
    }
}
