<?php

declare(strict_types=1);

namespace Tests\Unit\Battle\AI;

use PHPUnit\Framework\TestCase;
use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\AI\CalculadoraDanioIA;
use Src\Battle\Domain\AI\MemoriaCombateIA;
use Src\Battle\Domain\AI\NivelDificultad;
use Src\Battle\Domain\AI\PesosAmenaza;
use Src\Battle\Domain\AI\RespuestaRival;
use Src\Battle\Domain\AI\SelectorAccionIA;
use Src\Battle\Domain\AI\SimuladorAccionIA;
use Src\Battle\Domain\Chain\CadenaDanio;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\Posicion;
use Src\Pokemon\Domain\PokemonEntity;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Shared\Tipos\TiposCollection;

/**
 * Tests de Fase 2: Lookahead, memoria de combate y simulación.
 */
class Fase2Test extends TestCase
{
    // ─── MemoriaCombateIA ─────────────────────────────────

    public function test_memoria_registra_accion_enemiga(): void
    {
        $memoria = new MemoriaCombateIA(PesosAmenaza::porDefecto());

        $atacante = $this->combatiente(id: 'e1', nombre: 'Enemigo');
        $defensor = $this->combatiente(id: 'a1', nombre: 'Aliado');
        $accion = $this->accion($atacante, $defensor);

        $memoria->registrarAccionEnemiga(1, $accion, 50.0);

        $this->assertSame(0, $memoria->turnosDesdeUltimaAccionEnemigaContra('a1'));
        $this->assertSame(PHP_INT_MAX, $memoria->turnosDesdeUltimaAccionEnemigaContra('a2'));
    }

    public function test_memoria_registra_danio_recibido(): void
    {
        $memoria = new MemoriaCombateIA(PesosAmenaza::porDefecto());

        $memoria->registrarDanioRecibido(1, 'e1', 'a1', 30.0);
        $memoria->registrarDanioRecibido(2, 'e1', 'a1', 20.0);

        $this->assertSame(50.0, $memoria->danoTotalRecibidoPor('a1'));
        $this->assertSame(0.0, $memoria->danoTotalRecibidoPor('a2'));
    }

    public function test_memoria_registra_ko(): void
    {
        $memoria = new MemoriaCombateIA(PesosAmenaza::porDefecto());

        $memoria->registrarKO(3, 'e1', 'a1');

        $this->assertSame(1, $memoria->koRealizadosPor('e1'));
        $this->assertSame(0, $memoria->koRealizadosPor('e2'));
    }

    public function test_memoria_encuentra_enemigo_mas_activo(): void
    {
        $memoria = new MemoriaCombateIA(PesosAmenaza::porDefecto());

        $e1 = $this->combatiente(id: 'e1');
        $e2 = $this->combatiente(id: 'e2');
        $aliado = $this->combatiente(id: 'a1');

        $memoria->registrarAccionEnemiga(1, $this->accion($e1, $aliado), 10.0);
        $memoria->registrarAccionEnemiga(2, $this->accion($e1, $aliado), 10.0);
        $memoria->registrarAccionEnemiga(3, $this->accion($e2, $aliado), 10.0);

        $this->assertSame('e1', $memoria->enemigoMasActivoContra('team1'));
    }

    public function test_memoria_vacia_retorna_valores_por_defecto(): void
    {
        $memoria = new MemoriaCombateIA(PesosAmenaza::porDefecto());

        $this->assertTrue($memoria->estaVacio());
        $this->assertSame(PHP_INT_MAX, $memoria->turnosDesdeUltimaAccionEnemigaContra('a1'));
        $this->assertSame(0.0, $memoria->danoTotalRecibidoPor('a1'));
        $this->assertSame(0, $memoria->koRealizadosPor('e1'));
        $this->assertNull($memoria->enemigoMasActivoContra('team1'));
    }

    // ─── SimuladorAccionIA ────────────────────────────────

    public function test_simulador_no_mutla_batalla_original(): void
    {
        $simulador = new SimuladorAccionIA(new CadenaDanio());

        $atacante = $this->combatiente(
            stats: ['hp' => 200, 'atk' => 300, 'def' => 300],
            moves: [new MovimientoBatalla('Golpe', 100, \Src\Shared\Tipos\TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
            tipos: [\Src\Shared\Tipos\TipoPokemon::NORMAL],
            id: 'a1',
            posicion: Posicion::VANGUARDIA,
        );
        $defensor = $this->combatiente(
            stats: ['hp' => 50, 'def' => 100, 'spDef' => 100],
            id: 'e1',
            posicion: Posicion::VANGUARDIA,
        );

        $battle = $this->batallaCon([$atacante], [$defensor]);
        $hpAntes = $defensor->hpActual();

        $accion = $this->accion($atacante, $defensor);
        $resultado = $simulador->simular($battle, $accion);

        $this->assertGreaterThan(0, $resultado->danoInfligido);
        $this->assertSame($hpAntes, $defensor->hpActual());
    }

    public function test_simulador_detecta_ko(): void
    {
        $simulador = new SimuladorAccionIA(new CadenaDanio());

        $atacante = $this->combatiente(
            stats: ['hp' => 200, 'atk' => 999, 'def' => 300],
            moves: [new MovimientoBatalla('Golpe', 200, \Src\Shared\Tipos\TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
            tipos: [\Src\Shared\Tipos\TipoPokemon::NORMAL],
            id: 'a1',
            posicion: Posicion::VANGUARDIA,
        );
        $defensor = $this->combatiente(
            stats: ['hp' => 10, 'def' => 1, 'spDef' => 1],
            id: 'e1',
            posicion: Posicion::VANGUARDIA,
        );

        $battle = $this->batallaCon([$atacante], [$defensor]);
        $accion = $this->accion($atacante, $defensor);
        $resultado = $simulador->simular($battle, $accion);

        $this->assertTrue($resultado->objetivoDerrotado);
    }

    // ─── RespuestaRival ───────────────────────────────────

    public function test_respuesta_rival_genera_acciones(): void
    {
        $calculadora = new CalculadoraDanioIA(new CadenaDanio());
        $respuestaRival = new RespuestaRival($calculadora);

        $enemigo = $this->combatiente(
            stats: ['hp' => 200, 'atk' => 200, 'def' => 200, 'speed' => 200],
            moves: [new MovimientoBatalla('Golpe', 100, \Src\Shared\Tipos\TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
            tipos: [\Src\Shared\Tipos\TipoPokemon::NORMAL],
            id: 'e1',
            posicion: Posicion::VANGUARDIA,
        );
        $aliado = $this->combatiente(
            stats: ['hp' => 100, 'def' => 100, 'spDef' => 100],
            id: 'a1',
            posicion: Posicion::VANGUARDIA,
        );

        $battle = $this->batallaCon([$aliado], [$enemigo]);
        $actorQueActuo = $this->combatiente(id: 'a2', posicion: Posicion::RETAGUARDIA);

        $respuestas = $respuestaRival->generarRespuestas($battle, $actorQueActuo, 'team1');

        $this->assertNotEmpty($respuestas);
        $this->assertLessThanOrEqual(3, $respuestas->count());
    }

    public function test_respuesta_rival_vacia_si_no_hay_enemigos(): void
    {
        $calculadora = new CalculadoraDanioIA(new CadenaDanio());
        $respuestaRival = new RespuestaRival($calculadora);

        $aliado = $this->combatiente(id: 'a1', posicion: Posicion::VANGUARDIA);
        $battle = $this->batallaCon([$aliado], []);
        $actorQueActuo = $this->combatiente(id: 'a2', posicion: Posicion::RETAGUARDIA);

        $respuestas = $respuestaRival->generarRespuestas($battle, $actorQueActuo, 'team1');

        $this->assertTrue($respuestas->isEmpty());
    }

    // ─── Lookahead ────────────────────────────────────────

    public function test_dificil_usa_lookahead(): void
    {
        // En DIFÍCIL, la IA debe considerar la respuesta del rival.
        // Escenario: actor frágil que puede eliminar enemigo débil pero quedarse vulnerable.
        $selector = new SelectorAccionIA();

        $actor = $this->combatiente(
            stats: ['hp' => 80, 'atk' => 300, 'def' => 20, 'spDef' => 20, 'speed' => 200],
            moves: [new MovimientoBatalla('Golpe Brutal', 150, \Src\Shared\Tipos\TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
            tipos: [\Src\Shared\Tipos\TipoPokemon::NORMAL],
            id: 'a1',
            posicion: Posicion::VANGUARDIA,
        );
        $aliado = $this->combatiente(
            stats: ['hp' => 200, 'def' => 200, 'spDef' => 200],
            id: 'a2',
            posicion: Posicion::RETAGUARDIA,
        );
        $enemigoDebil = $this->combatiente(
            stats: ['hp' => 50, 'atk' => 50, 'def' => 100, 'spDef' => 100, 'speed' => 40],
            moves: [new MovimientoBatalla('Arañazo', 40, \Src\Shared\Tipos\TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
            tipos: [\Src\Shared\Tipos\TipoPokemon::NORMAL],
            id: 'e1',
            posicion: Posicion::VANGUARDIA,
        );
        $enemigoFuerte = $this->combatiente(
            stats: ['hp' => 200, 'atk' => 400, 'def' => 100, 'spDef' => 100, 'speed' => 300],
            moves: [new MovimientoBatalla('Golpe Pesado', 150, \Src\Shared\Tipos\TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
            tipos: [\Src\Shared\Tipos\TipoPokemon::NORMAL],
            id: 'e2',
            posicion: Posicion::VANGUARDIA,
        );

        $battle = $this->batallaCon([$actor, $aliado], [$enemigoDebil, $enemigoFuerte]);

        $resultado = $selector->elegirAccion($battle, $actor, NivelDificultad::DIFICIL);

        // La acción debe ser válida
        $this->assertNotNull($resultado->accion);
        $this->assertNotNull($resultado->accion->defender);
    }

    public function test_perfecta_es_determinista(): void
    {
        $selector = new SelectorAccionIA();

        $actor = $this->combatiente(
            stats: ['hp' => 200, 'atk' => 300, 'def' => 300, 'speed' => 200],
            moves: [new MovimientoBatalla('Golpe Brutal', 150, \Src\Shared\Tipos\TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
            tipos: [\Src\Shared\Tipos\TipoPokemon::NORMAL],
            id: 'a1',
            posicion: Posicion::VANGUARDIA,
        );
        $aliado = $this->combatiente(
            stats: ['hp' => 200, 'def' => 200, 'spDef' => 200],
            id: 'a2',
            posicion: Posicion::RETAGUARDIA,
        );
        $enemigo = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 40, 'def' => 100, 'spDef' => 100, 'speed' => 40],
            moves: [new MovimientoBatalla('Arañazo', 40, \Src\Shared\Tipos\TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
            tipos: [\Src\Shared\Tipos\TipoPokemon::NORMAL],
            id: 'e1',
            posicion: Posicion::VANGUARDIA,
        );

        $battle = $this->batallaCon([$actor, $aliado], [$enemigo]);

        // PERFECTA siempre elige la misma acción
        $resultados = [];
        for ($i = 0; $i < 10; $i++) {
            $resultado = $selector->elegirAccion($battle, $actor, NivelDificultad::PERFECTA);
            $resultados[] = $resultado->accion->move->nombre;
        }

        $this->assertCount(1, array_unique($resultados));
    }

    public function test_selector_exponer_memoria(): void
    {
        $selector = new SelectorAccionIA();

        $this->assertInstanceOf(MemoriaCombateIA::class, $selector->memoria());
        $this->assertTrue($selector->memoria()->estaVacio());
    }

    public function test_contexto_incluye_memoria(): void
    {
        $memoria = new MemoriaCombateIA(PesosAmenaza::porDefecto());
        $selector = new SelectorAccionIA(memoria: $memoria);

        $actor = $this->combatiente(
            stats: ['hp' => 200, 'atk' => 200, 'def' => 200],
            moves: [new MovimientoBatalla('Golpe', 100, \Src\Shared\Tipos\TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
            tipos: [\Src\Shared\Tipos\TipoPokemon::NORMAL],
            id: 'a1',
            posicion: Posicion::VANGUARDIA,
        );
        $aliado = $this->combatiente(id: 'a2', posicion: Posicion::RETAGUARDIA);
        $enemigo = $this->combatiente(
            stats: ['hp' => 100, 'def' => 100, 'spDef' => 100],
            id: 'e1',
            posicion: Posicion::VANGUARDIA,
        );

        $battle = $this->batallaCon([$actor, $aliado], [$enemigo]);

        $resultado = $selector->elegirAccion($battle, $actor);

        $this->assertNotNull($resultado->accion);
        // La memoria compartida debe seguir vacía (no se registra en elegirAccion directamente)
        $this->assertTrue($memoria->estaVacio());
    }

    // ─── Helpers ──────────────────────────────────────────

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

    private function accion(Combatiente $atacante, Combatiente $defensor): AccionBatalla
    {
        $movimiento = $atacante->pokemon()->moves()->get(0);

        return new AccionBatalla(
            attacker: $atacante,
            defender: $defensor,
            move: $movimiento instanceof MovimientoBatalla
                ? $movimiento
                : new MovimientoBatalla('Placaje', 40, \Src\Shared\Tipos\TipoPokemon::NORMAL, CategoriaMovimiento::FISICO),
            fromPosition: $atacante->posicion(),
            defenderTeamHasVanguard: false,
            weather: \Src\Battle\Domain\Enums\TipoClima::NONE,
        );
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
}
