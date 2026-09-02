<?php

declare(strict_types=1);

namespace Tests\Unit\Battle\AI;

use PHPUnit\Framework\TestCase;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\AI\SelectorAccionIA;
use Src\Battle\Domain\AI\ValueObjects\ResultadoDecision;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\Posicion;
use Src\Pokemon\Domain\PokemonEntity;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Shared\Tipos\TiposCollection;

/**
 * Testea las decisiones contextuales de la IA de combate (Fase 1).
 */
class SelectorAccionIATest extends TestCase
{
    private SelectorAccionIA $selector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->selector = new SelectorAccionIA();
    }

    public function test_elegir_accion_prioriza_ko_sobre_amenaza_critica(): void
    {
        // Actor con un golpe fuerte que hace KO a ambos enemigos.
        $actor = $this->combatiente(
            stats: ['hp' => 200, 'atk' => 300, 'def' => 300, 'speed' => 200],
            moves: [new MovimientoBatalla('Golpe Brutal', 150, \Src\Shared\Tipos\TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
            tipos: [\Src\Shared\Tipos\TipoPokemon::NORMAL],
            id: 'a1',
            nombre: 'Actor',
            posicion: Posicion::VANGUARDIA,
        );
        $aliado = $this->combatiente(
            stats: ['hp' => 100, 'def' => 30, 'spDef' => 30],
            id: 'a2',
            nombre: 'Aliado',
            posicion: Posicion::RETAGUARDIA,
        );

        // Enemigo A: débil (amenaza baja)
        $enemigoA = $this->combatiente(
            stats: ['hp' => 50, 'atk' => 50, 'def' => 100, 'spAtk' => 20, 'spDef' => 100, 'speed' => 50],
            moves: [new MovimientoBatalla('Arañazo', 40, \Src\Shared\Tipos\TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
            tipos: [\Src\Shared\Tipos\TipoPokemon::NORMAL],
            id: 'e1',
            nombre: 'EnemigoA',
            posicion: Posicion::VANGUARDIA,
        );
        // Enemigo B: amenaza crítica (alto daño al aliado → ofensiva alta)
        $enemigoB = $this->combatiente(
            stats: ['hp' => 50, 'atk' => 300, 'def' => 100, 'spAtk' => 20, 'spDef' => 100, 'speed' => 200],
            moves: [new MovimientoBatalla('Golpe Pesado', 150, \Src\Shared\Tipos\TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
            tipos: [\Src\Shared\Tipos\TipoPokemon::NORMAL],
            id: 'e2',
            nombre: 'EnemigoB',
            posicion: Posicion::VANGUARDIA,
        );

        $battle = $this->batallaCon([$actor, $aliado], [$enemigoA, $enemigoB], $actor);

        $resultado = $this->selector->elegirAccion($battle, $actor);

        $this->assertSame('e2', $resultado->accion->defender->id());
    }

    public function test_elegir_accion_prioriza_ko_garantizado_cuando_no_hay_amenaza_urgente(): void
    {
        // Actor con un movimiento que hace KO y uno de estado/setup contra el único enemigo vivo.
        $actor = $this->combatiente(
            stats: ['hp' => 200, 'atk' => 300, 'def' => 300, 'spAtk' => 300, 'spDef' => 300, 'speed' => 200],
            moves: [
                new MovimientoBatalla('Golpe Brutal', 150, \Src\Shared\Tipos\TipoPokemon::NORMAL, CategoriaMovimiento::FISICO),
                new MovimientoBatalla('Ponzoña', 0, \Src\Shared\Tipos\TipoPokemon::VENENO, CategoriaMovimiento::ESTADO, statusEffect: EstadoPokemon::POISON),
            ],
            tipos: [\Src\Shared\Tipos\TipoPokemon::NORMAL],
            id: 'a1',
            nombre: 'Actor',
            posicion: Posicion::VANGUARDIA,
        );
        $aliado = $this->combatiente(
            stats: ['hp' => 200, 'def' => 300, 'spDef' => 300],
            id: 'a2',
            nombre: 'Aliado',
            posicion: Posicion::RETAGUARDIA,
        );
        $enemigo = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 40, 'def' => 100, 'spAtk' => 20, 'spDef' => 100, 'speed' => 40],
            moves: [new MovimientoBatalla('Arañazo', 40, \Src\Shared\Tipos\TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
            tipos: [\Src\Shared\Tipos\TipoPokemon::NORMAL],
            id: 'e1',
            nombre: 'Enemigo',
            posicion: Posicion::VANGUARDIA,
        );

        $battle = $this->batallaCon([$actor, $aliado], [$enemigo], $actor);

        $resultado = $this->selector->elegirAccion($battle, $actor);

        // Debe elegir el KO garantizado (Golpe Brutal) en vez del setup/estado.
        $this->assertSame('Golpe Brutal', $resultado->accion->move->nombre);
    }

    public function test_elegir_accion_prefiere_ko_sobre_setup_con_ultimo_enemigo(): void
    {
        $actor = $this->combatiente(
            stats: ['hp' => 200, 'atk' => 300, 'def' => 300, 'spAtk' => 300, 'spDef' => 300, 'speed' => 200],
            moves: [
                new MovimientoBatalla('Golpe Brutal', 150, \Src\Shared\Tipos\TipoPokemon::NORMAL, CategoriaMovimiento::FISICO),
                new MovimientoBatalla('Foco', 0, \Src\Shared\Tipos\TipoPokemon::NORMAL, CategoriaMovimiento::ESTADO, selfStatChanges: [['stat' => 'attack', 'stages' => 2]]),
            ],
            tipos: [\Src\Shared\Tipos\TipoPokemon::NORMAL],
            id: 'a1',
            nombre: 'Actor',
            posicion: Posicion::VANGUARDIA,
        );
        $aliado = $this->combatiente(
            stats: ['hp' => 200, 'def' => 300, 'spDef' => 300],
            id: 'a2',
            nombre: 'Aliado',
            posicion: Posicion::RETAGUARDIA,
        );
        // Único enemigo con 10% HP (hp bajo) → KO seguro
        $enemigo = $this->combatiente(
            stats: ['hp' => 20, 'atk' => 40, 'def' => 100, 'spAtk' => 20, 'spDef' => 100, 'speed' => 40],
            moves: [new MovimientoBatalla('Arañazo', 40, \Src\Shared\Tipos\TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
            tipos: [\Src\Shared\Tipos\TipoPokemon::NORMAL],
            id: 'e1',
            nombre: 'Enemigo',
            posicion: Posicion::VANGUARDIA,
        );

        $battle = $this->batallaCon([$actor, $aliado], [$enemigo], $actor);

        $resultado = $this->selector->elegirAccion($battle, $actor);

        $this->assertSame('Golpe Brutal', $resultado->accion->move->nombre);
    }

    public function test_elegir_accion_retorna_amenazas_y_evaluaciones(): void
    {
        $actor = $this->combatiente(
            stats: ['hp' => 200, 'atk' => 200, 'def' => 200, 'speed' => 200],
            moves: [new MovimientoBatalla('Golpe Brutal', 100, \Src\Shared\Tipos\TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
            tipos: [\Src\Shared\Tipos\TipoPokemon::NORMAL],
            id: 'a1',
            nombre: 'Actor',
            posicion: Posicion::VANGUARDIA,
        );
        $aliado = $this->combatiente(
            stats: ['hp' => 200, 'def' => 200, 'spDef' => 200],
            id: 'a2',
            nombre: 'Aliado',
            posicion: Posicion::RETAGUARDIA,
        );
        $enemigo = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 50, 'def' => 100, 'spAtk' => 20, 'spDef' => 100, 'speed' => 50],
            moves: [new MovimientoBatalla('Arañazo', 40, \Src\Shared\Tipos\TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
            tipos: [\Src\Shared\Tipos\TipoPokemon::NORMAL],
            id: 'e1',
            nombre: 'Enemigo',
            posicion: Posicion::VANGUARDIA,
        );

        $battle = $this->batallaCon([$actor, $aliado], [$enemigo], $actor);

        $resultado = $this->selector->elegirAccion($battle, $actor);

        $this->assertInstanceOf(ResultadoDecision::class, $resultado);
        $this->assertNotEmpty($resultado->amenazas);
        $this->assertNotEmpty($resultado->evaluaciones);
    }

    public function test_elegir_accion_penaliza_supervivencia_cuando_el_actor_muere_tras_el_ko(): void
    {
        // Actor frágil: una vez elimina al enemigo débil, el enemigo fuerte lo tumba.
        $actor = $this->combatiente(
            stats: ['hp' => 80, 'atk' => 300, 'def' => 20, 'spDef' => 20, 'speed' => 200],
            moves: [new MovimientoBatalla('Golpe Brutal', 150, \Src\Shared\Tipos\TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
            tipos: [\Src\Shared\Tipos\TipoPokemon::NORMAL],
            id: 'a1',
            nombre: 'Actor',
            posicion: Posicion::VANGUARDIA,
        );
        $aliado = $this->combatiente(
            stats: ['hp' => 200, 'def' => 200, 'spDef' => 200],
            id: 'a2',
            nombre: 'Aliado',
            posicion: Posicion::RETAGUARDIA,
        );
        $enemigoDebil = $this->combatiente(
            stats: ['hp' => 50, 'atk' => 50, 'def' => 100, 'spDef' => 100, 'speed' => 40],
            moves: [new MovimientoBatalla('Arañazo', 40, \Src\Shared\Tipos\TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
            tipos: [\Src\Shared\Tipos\TipoPokemon::NORMAL],
            id: 'e1',
            nombre: 'EnemigoDebil',
            posicion: Posicion::VANGUARDIA,
        );
        $enemigoFuerte = $this->combatiente(
            stats: ['hp' => 200, 'atk' => 400, 'def' => 100, 'spDef' => 100, 'speed' => 300],
            moves: [new MovimientoBatalla('Golpe Pesado', 150, \Src\Shared\Tipos\TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
            tipos: [\Src\Shared\Tipos\TipoPokemon::NORMAL],
            id: 'e2',
            nombre: 'EnemigoFuerte',
            posicion: Posicion::RETAGUARDIA,
        );

        $battle = $this->batallaCon([$actor, $aliado], [$enemigoDebil, $enemigoFuerte], $actor);

        $resultado = $this->selector->elegirAccion($battle, $actor);

        $evalDebil = null;
        foreach ($resultado->evaluaciones as $ev) {
            if ($ev->accion->defender->id() === 'e1') {
                $evalDebil = $ev;
                break;
            }
        }
        $this->assertNotNull($evalDebil);
        $this->assertSame(-50.0, $evalDebil->survivalValue);
    }

    public function test_elegir_accion_premia_supervivencia_cuando_el_actor_no_queda_amenazado(): void
    {
        // Actor robusto: no hay enemigo restante capaz de tumbarlo.
        $actor = $this->combatiente(
            stats: ['hp' => 300, 'atk' => 300, 'def' => 300, 'spDef' => 300, 'speed' => 200],
            moves: [new MovimientoBatalla('Golpe Brutal', 150, \Src\Shared\Tipos\TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
            tipos: [\Src\Shared\Tipos\TipoPokemon::NORMAL],
            id: 'a1',
            nombre: 'Actor',
            posicion: Posicion::VANGUARDIA,
        );
        $aliado = $this->combatiente(
            stats: ['hp' => 200, 'def' => 200, 'spDef' => 200],
            id: 'a2',
            nombre: 'Aliado',
            posicion: Posicion::RETAGUARDIA,
        );
        $enemigo = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 40, 'def' => 100, 'spDef' => 100, 'speed' => 40],
            moves: [new MovimientoBatalla('Arañazo', 40, \Src\Shared\Tipos\TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
            tipos: [\Src\Shared\Tipos\TipoPokemon::NORMAL],
            id: 'e1',
            nombre: 'Enemigo',
            posicion: Posicion::VANGUARDIA,
        );

        $battle = $this->batallaCon([$actor, $aliado], [$enemigo], $actor);

        $resultado = $this->selector->elegirAccion($battle, $actor);

        $eval = null;
        foreach ($resultado->evaluaciones as $ev) {
            if ($ev->accion->defender->id() === 'e1') {
                $eval = $ev;
                break;
            }
        }
        $this->assertNotNull($eval);
        $this->assertSame(50.0, $eval->survivalValue);
    }

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
     * @param  Combatiente[]  $aliados
     * @param  Combatiente[]  $enemigos
     */
    private function batallaCon(array $aliados, array $enemigos, Combatiente $actor): AgregadoBatalla
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
}
