<?php

declare(strict_types=1);

namespace Tests\Feature\Battle;

use App\Livewire\Combate;
use App\Support\BattleSessionService;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Equipo\EfectoEquipoEspejoMagico;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\Posicion;
use Src\Battle\Domain\ValueObjects\PerfilMovimiento;
use Src\Battle\Presentation\DTOAccionBatalla;
use Src\Battle\Presentation\DTOMovimientoBatalla;
use Src\Pokemon\Domain\PokemonEntity;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Shared\Tipos\TipoPokemon;
use Src\Shared\Tipos\TiposCollection;
use Tests\TestCase;

/**
 * Regresión TASK-002: el mensaje y el evento `status` de Combate deben
 * derivarse del delta real de estado, no del movimiento declarado.
 */
class EstadoAplicadoLivewireTest extends TestCase
{
    public function test_dragoaliento_que_falla_probabilidad_no_muestra_mensaje_de_paralisis(): void
    {
        [$battleId, $movimiento] = $this->prepararBatalla(probabilidadEstado: 0.0);
        $component = $this->montarYEjecutar($battleId, $movimiento);

        $battle = app(BattleSessionService::class)->cargar($battleId);
        $defensor = $battle->team2->findCombatantById('d1');

        $this->assertSame(EstadoPokemon::NONE, $defensor->estado());
        $this->assertFalse($this->logContiene($component->get('log'), 'Defensor sufre parálisis'));
        $this->assertNull($this->eventoStatusDe($component->get('eventos'), 'd1'));
    }

    public function test_dragoaliento_que_aplica_muestra_mensaje_de_paralisis(): void
    {
        [$battleId, $movimiento] = $this->prepararBatalla(probabilidadEstado: 1.0);
        $component = $this->montarYEjecutar($battleId, $movimiento);

        $battle = app(BattleSessionService::class)->cargar($battleId);
        $defensor = $battle->team2->findCombatantById('d1');
        $evento = $this->eventoStatusDe($component->get('eventos'), 'd1');

        $this->assertSame(EstadoPokemon::PARALYSIS, $defensor->estado());
        $this->assertTrue($this->logContiene($component->get('log'), 'Defensor sufre parálisis!'));
        $this->assertNotNull($evento);
        $this->assertSame('none', $evento['cambios']['d1']['previo']['status']);
        $this->assertSame('paralysis', $evento['cambios']['d1']['actual']['status']);
        $this->assertStringContainsString('parálisis', $evento['texto']);
    }

    public function test_evento_status_no_se_emite_sin_cambio_de_estado(): void
    {
        [$battleId, $movimiento] = $this->prepararBatalla(probabilidadEstado: 0.0);
        $component = $this->montarYEjecutar($battleId, $movimiento);

        $eventosStatus = array_values(array_filter(
            $component->get('eventos'),
            static fn (array $evento): bool => ($evento['tipo'] ?? null) === 'status',
        ));

        $this->assertSame([], $eventosStatus);
    }

    public function test_evento_status_se_emite_cuando_espejo_magico_paraliza_al_atacante(): void
    {
        [$battleId, $movimiento] = $this->prepararBatalla(probabilidadEstado: 1.0, espejoMagico: true);
        $component = $this->montarYEjecutar($battleId, $movimiento);

        $battle = app(BattleSessionService::class)->cargar($battleId);
        $atacante = $battle->team1->findCombatantById('a1');
        $defensor = $battle->team2->findCombatantById('d1');
        $evento = $this->eventoStatusDe($component->get('eventos'), 'a1');
        $log = $component->get('log');

        $this->assertSame(EstadoPokemon::PARALYSIS, $atacante->estado());
        $this->assertSame(EstadoPokemon::NONE, $defensor->estado());
        $this->assertTrue($this->logContiene($log, '¡Espejo Mágico devuelve Dragoaliento a Atacante!'));
        $this->assertFalse($this->logContiene($log, 'Defensor sufre parálisis'));
        $this->assertNotNull($evento);
        $this->assertSame('none', $evento['cambios']['a1']['previo']['status']);
        $this->assertSame('paralysis', $evento['cambios']['a1']['actual']['status']);
        $this->assertStringContainsString('Atacante sufre parálisis', $evento['texto']);
    }

    public function test_mensaje_y_evento_coinciden_con_el_estado_final(): void
    {
        [$battleId, $movimiento] = $this->prepararBatalla(probabilidadEstado: 1.0);
        $component = $this->montarYEjecutar($battleId, $movimiento);

        $battle = app(BattleSessionService::class)->cargar($battleId);
        $defensor = $battle->team2->findCombatantById('d1');
        $evento = $this->eventoStatusDe($component->get('eventos'), 'd1');

        $this->assertNotNull($evento);
        $this->assertSame($defensor->estado()->value, $evento['cambios']['d1']['actual']['status']);
        $this->assertSame($defensor->estado()->label(), 'parálisis');
        $this->assertStringContainsString($defensor->estado()->label(), $evento['texto']);
        $this->assertStringContainsString($defensor->estado()->label(), implode("\n", $component->get('log')));
    }

    /**
     * Construye un 1v1 con un atacante más rápido y guarda la batalla en sesión.
     *
     * @return array{0: string, 1: MovimientoBatalla}
     */
    private function prepararBatalla(
        float $probabilidadEstado,
        bool $espejoMagico = false,
    ): array {
        $movimiento = new MovimientoBatalla(
            'Dragoaliento',
            60,
            TipoPokemon::DRAGON,
            CategoriaMovimiento::ESPECIAL,
            statusEffect: EstadoPokemon::PARALYSIS,
            perfil: new PerfilMovimiento(probabilidadEstado: $probabilidadEstado),
        );

        $atacante = $this->combatiente(
            id: 'a1',
            nombre: 'Atacante',
            tipos: [TipoPokemon::DRAGON],
            stats: ['hp' => 500, 'atk' => 100, 'def' => 100, 'spAtk' => 100, 'spDef' => 100, 'speed' => 200],
            moves: [$movimiento],
        );
        $defensor = $this->combatiente(
            id: 'd1',
            nombre: 'Defensor',
            tipos: [TipoPokemon::AGUA],
            stats: ['hp' => 1000, 'atk' => 50, 'def' => 500, 'spAtk' => 50, 'spDef' => 500, 'speed' => 10],
        );

        $team1 = new EquipoBatalla('T1');
        $team1->agregarCombatiente($atacante, Posicion::VANGUARDIA);

        $team2 = new EquipoBatalla('T2');
        $team2->agregarCombatiente($defensor, Posicion::VANGUARDIA);
        if ($espejoMagico) {
            $team2->agregarEfecto(new EfectoEquipoEspejoMagico('espejo_magico', 'd1'));
        }

        $battleId = 'battle_estado_'.uniqid();
        app(BattleSessionService::class)->guardar($battleId, new AgregadoBatalla($team1, $team2));

        return [$battleId, $movimiento];
    }

    /**
     * @param  array{hp?: int, atk?: int, def?: int, spAtk?: int, spDef?: int, speed?: int}  $stats
     * @param  list<MovimientoBatalla>  $moves
     * @param  list<TipoPokemon>  $tipos
     */
    private function combatiente(
        string $id,
        string $nombre,
        array $tipos,
        array $stats,
        array $moves = [],
    ): Combatiente {
        $pokemon = new PokemonEntity(
            stats: new StatsValue(
                hp: $stats['hp'] ?? 100,
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

        $combatiente = new Combatiente($pokemon, Posicion::VANGUARDIA);
        $combatiente->setId($id);
        $combatiente->setNombre($nombre);

        return $combatiente;
    }

    private function montarYEjecutar(string $battleId, MovimientoBatalla $movimiento): Testable
    {
        $component = Livewire::withQueryParams(['battle_id' => $battleId])->test(Combate::class);

        $session = app(BattleSessionService::class);
        $battle = $session->cargar($battleId);
        $battle->setPendingAction(new DTOAccionBatalla(
            type: 'attack',
            actorId: 'a1',
            defenderId: 'd1',
            attackerNombre: 'Atacante',
            move: DTOMovimientoBatalla::desdeDominio($movimiento),
        ));
        $session->guardar($battleId, $battle);

        return $component->call('commitAction');
    }

    /** @param list<string> $log */
    private function logContiene(array $log, string $needle): bool
    {
        return str_contains(implode("\n", $log), $needle);
    }

    /**
     * @param  list<array<string, mixed>>  $eventos
     * @return array<string, mixed>|null
     */
    private function eventoStatusDe(array $eventos, string $refId): ?array
    {
        foreach ($eventos as $evento) {
            if (($evento['tipo'] ?? null) !== 'status') {
                continue;
            }

            if (isset($evento['cambios'][$refId])) {
                return $evento;
            }
        }

        return null;
    }
}
