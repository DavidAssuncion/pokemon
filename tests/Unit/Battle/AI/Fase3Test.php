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
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\Posicion;
use Src\Battle\Infrastructure\FabricaBatallaMock;
use Src\Pokemon\Domain\PokemonEntity;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Shared\Tipos\TipoPokemon;
use Src\Shared\Tipos\TiposCollection;

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

    public function test_elige_movimiento_que_dania_hp_real_aunque_haga_menos_dano_bruto(): void
    {
        // Actor con dos movimientos: uno físico muy potente y uno especial moderado.
        $actor = $this->combatiente(
            stats: ['hp' => 300, 'atk' => 200, 'def' => 100, 'spAtk' => 200, 'spDef' => 100, 'speed' => 200],
            moves: [
                new MovimientoBatalla('Golpe Físico', 150, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO),
                new MovimientoBatalla('Golpe Especial', 90, TipoPokemon::NORMAL, CategoriaMovimiento::ESPECIAL),
            ],
            tipos: [TipoPokemon::NORMAL],
            id: 'a1',
            nombre: 'Actor',
            posicion: Posicion::VANGUARDIA,
        );
        $aliado = $this->combatiente(
            stats: ['hp' => 200, 'def' => 100, 'spDef' => 100],
            id: 'a2',
            nombre: 'Aliado',
            posicion: Posicion::RETAGUARDIA,
        );

        // Enemigo con barrera FÍSICA enorme (def muy alta) y barrera ESPECIAL pequeña (spDef baja).
        // El golpe físico bruto es mayor pero se pierde absorbiendo la barrera física;
        // el golpe especial hace menos daño bruto pero desborda más al HP real.
        $enemigo = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 50, 'def' => 200, 'spAtk' => 20, 'spDef' => 10, 'speed' => 40],
            moves: [new MovimientoBatalla('Arañazo', 40, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
            tipos: [TipoPokemon::NORMAL],
            id: 'e1',
            nombre: 'Enemigo',
            posicion: Posicion::VANGUARDIA,
        );

        $battle = $this->batallaCon([$actor, $aliado], [$enemigo]);

        $selector = new SelectorAccionIA();
        $resultado = $selector->elegirAccion($battle, $actor, NivelDificultad::PERFECTA);

        // Debe elegir el movimiento ESPECIAL: daña el HP real, acercando el KO/el final del combate,
        // en lugar del físico que se absorbe por la enorme barrera física.
        $this->assertSame('Golpe Especial', $resultado->accion->move->nombre);
    }

    /**
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

        return $combatant;
    }

    /**
     * @param  Combatiente[]  $aliados
     * @param  Combatiente[]  $enemigos
     */
    private function batallaCon(array $aliados, array $enemigos): AgregadoBatalla
    {
        $team1 = new EquipoBatalla('T1');
        foreach ($aliados as $a) {
            $team1->agregarCombatiente($a, $a->posicion());
        }

        $team2 = new EquipoBatalla('T2');
        foreach ($enemigos as $e) {
            $team2->agregarCombatiente($e, $e->posicion());
        }

        return new AgregadoBatalla($team1, $team2);
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
