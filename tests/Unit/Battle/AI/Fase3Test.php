<?php

declare(strict_types=1);

namespace Tests\Unit\Battle\AI;

use PHPUnit\Framework\TestCase;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\AI\MemoriaCombateIA;
use Src\Battle\Domain\AI\NivelDificultad;
use Src\Battle\Domain\AI\PesosAmenaza;
use Src\Battle\Domain\AI\SelectorAccionIA;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Infrastructure\FabricaBatallaMock;

/**
 * Tests de Fase 3: integración de memoria, dificultad y equipos en el flujo real.
 */
class Fase3Test extends TestCase
{
    // ─── Memoria alimentada desde el flujo real ────────────

    public function test_ejecutar_batalla_alimenta_memoria_con_acciones(): void
    {
        $battle = (new FabricaBatallaMock())->createBattle();

        $battle->ejecutarBatalla();

        // La memoria interna del selector debe haberse alimentado con acciones y KOs
        $selector = $this->accederPrivado($battle, 'getSelectorAccion')();
        $this->assertInstanceOf(SelectorAccionIA::class, $selector);
        $this->assertFalse($selector->memoria()->estaVacio());
    }

    public function test_ejecutar_batalla_registra_ko_en_memoria(): void
    {
        $battle = (new FabricaBatallaMock())->createBattle();

        $battle->ejecutarBatalla();

        $selector = $this->accederPrivado($battle, 'getSelectorAccion')();
        $memoria = $selector->memoria();

        $totalKOs = 0;
        foreach (['player_1', 'player_2', 'player_3', 'enemy_1', 'enemy_2', 'enemy_3'] as $id) {
            $totalKOs += $memoria->koRealizadosPor($id);
        }

        // La batalla termina cuando un equipo se debilita, por lo que debe haber al menos un KO
        $this->assertGreaterThan(0, $totalKOs);
    }

    // ─── Dificultad configurable ──────────────────────────

    public function test_set_dificultad_acepta_los_tres_niveles(): void
    {
        $battle = (new FabricaBatallaMock())->createBattle();

        foreach ([NivelDificultad::NORMAL, NivelDificultad::DIFICIL, NivelDificultad::PERFECTA] as $nivel) {
            $battle->setDificultad($nivel);
            $this->assertInstanceOf(AgregadoBatalla::class, $battle);
        }
    }

    public function test_selector_acepta_memoria_inyectada(): void
    {
        $memoria = new MemoriaCombateIA(PesosAmenaza::porDefecto());
        $selector = new SelectorAccionIA(memoria: $memoria);

        $this->assertSame($memoria, $selector->memoria());
    }

    public function test_selector_acepta_servicios_inyectados(): void
    {
        // El selector con constructor por defecto (backward compat) funciona
        $selector = new SelectorAccionIA();
        $this->assertInstanceOf(MemoriaCombateIA::class, $selector->memoria());
    }

    // ─── equipos / contexto ───────────────────────────────

    public function test_selector_elige_objetivo_valido_para_team1(): void
    {
        $battle = (new FabricaBatallaMock())->createBattle();
        $actor = $battle->team1->combatants()[1]; // Giratina en vanguardia

        $resultado = $battle->elegirObjetivoPara($actor);

        $this->assertNotNull($resultado);
        $this->assertSame('team2', $this->equipoDe($battle, $resultado));
    }

    private function equipoDe(AgregadoBatalla $battle, Combatiente $combatiente): string
    {
        return $battle->team1->findCombatant($combatiente) !== null ? 'team1' : 'team2';
    }

    /**
     * Accede a un método privado vía Reflection (solo para test).
     *
     * @return callable
     */
    private function accederPrivado(object $objeto, string $metodo): callable
    {
        $ref = new \ReflectionMethod($objeto, $metodo);
        $ref->setAccessible(true);

        return fn (...$args) => $ref->invokeArgs($objeto, $args);
    }
}
