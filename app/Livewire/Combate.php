<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Presenters\MovesPreviewPresenter;
use App\Livewire\Presenters\PresentadorResultadoBatalla;
use App\Support\BattleSessionService;
use Livewire\Component;
use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\FaseCombate;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\FabricaBatallaInterface;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\ServicioEjecucionBatalla;
use Src\Battle\Presentation\DTOAccionBatalla;
use Src\Battle\Presentation\DTOMovimientoBatalla;

class Combate extends Component
{
    public string $battleId = '';

    public array $team1 = [];

    public array $team2 = [];

    public array $turnQueue = [];

    public array $currentMoves = [];

    public ?int $selectedMoveIdx = null;

    public string $phase = FaseCombate::INICIO->value;

    public int $round = 0;

    public array $log = [];

    public string $actingRefId = '';

    public bool $processing = false;

    public string $animAttackerId = '';

    public string $animDefenderId = '';

    public string $animAttackerNombre = '';

    public string $animDefenderNombre = '';

    public string $animMoveNombre = '';

    public int $animTick = 0;

    public string $weather = 'none';

    public array $rewards = [];

    public int $habitatId = 0;

    public ?int $selectedTargetTeam = null;

    public ?int $selectedTargetIdx = null;

    public string $selectedTargetRefId = '';

    private FabricaBatallaInterface $fabricaBatalla;

    private BattleSessionService $session;

    private ?ServicioEjecucionBatalla $servicioEjecucion = null;

    // ─── Lifecycle ───────────────────────────────────────────

    public function nuevaBatalla(): void
    {
        $this->battleId = $this->session->crearId();
        $this->initMockBattle();
    }

    /**
     * Livewire 3 ejecuta boot() en CADA request (montaje inicial y updates
     * posteriores), mientras que mount() solo corre en el primero. Resolver
     * aquí los servicios evita el Error "must not be accessed before
     * initialization" en los métodos invocados por wire en requests siguientes.
     * La sintaxis ??= es segura con propiedades tipadas no inicializadas
     * (semántica isset); una lectura directa lanzaría el mismo Error.
     */
    public function boot(): void
    {
        $this->fabricaBatalla ??= app(FabricaBatallaInterface::class);
        $this->session ??= app(BattleSessionService::class);
    }

    public function mount(): void
    {
        $battleId = request()->query('battle_id');
        if (is_string($battleId) && $battleId !== '') {
            $this->battleId = $battleId;
            $battle = $this->session->cargar($this->battleId);
            if ($battle !== null) {
                $this->servicioEjecucion = new ServicioEjecucionBatalla($battle->damageChain());
                $this->syncViewData($battle);
                $this->log[] = '¡Comienza la batalla!';
                $this->session->guardar($this->battleId, $battle);
                $this->nextActor();

                return;
            }
        }

        $this->nuevaBatalla();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.combate')
            ->extends('layouts.app')
            ->section('content');
    }

    // ─── Inicialización ──────────────────────────────────────

    private function initMockBattle(): void
    {
        $battle = $this->fabricaBatalla->createBattle();
        $this->servicioEjecucion = new ServicioEjecucionBatalla($battle->damageChain());
        $this->session->guardar($this->battleId, $battle);

        $this->syncViewData($battle);
        $this->session->guardar($this->battleId, $battle); // re-save after clearing log
        $this->log[] = '¡Comienza la batalla!';
        $this->nextActor();
    }

    // ─── Ciclo de turno ──────────────────────────────────────

    /**
     * Avanza al siguiente actor. Si la ronda terminó, inicia una nueva
     * y dispara efectos de fin/inicio de ronda.
     */
    public function nextActor(): void
    {
        $battle = $this->session->cargar($this->battleId);
        if ($battle === null) {
            return;
        }

        if (! $battle->turnManager()->bothTeamsAlive()) {
            $this->endBattle($battle);

            return;
        }

        // Si todos actuaron o es el inicio, avanzar ronda
        if (! $battle->turnManager()->hayAlgunoConAccionPendiente()) {
            $this->advanceRound($battle);
        }

        $actor = $battle->turnManager()->getNextActor();

        // Si aún así no hay actor (todos sin velocidad), forzar nueva ronda
        if ($actor === null) {
            $this->advanceRound($battle);
            $actor = $battle->turnManager()->getNextActor();
            if ($actor === null) {
                $this->endBattle($battle);

                return;
            }
        }

        $this->actingRefId = $actor->id();

        // Verificar si el actor puede actuar (sueño, hielo, parálisis, confusión)
        $statusCheck = $actor->puedeActuar();
        if (! $statusCheck->esPermitida()) {
            $this->log[] = "{$actor->nombre()} {$statusCheck->motivo()}!";
            if ($statusCheck->autoDanio() > 0) {
                $this->log[] = "¡{$actor->nombre()} se golpeó a sí mismo! ({$statusCheck->autoDanio()} daño)";
            }
            $battle->turnManager()->consumeAction($actor);
            $this->syncViewData($battle);
            $this->session->guardar($this->battleId, $battle);
            $this->nextActor();

            return;
        }
        if ($statusCheck->motivo() === 'despertó' || $statusCheck->motivo() === 'se descongeló') {
            $this->log[] = "¡{$actor->nombre()} {$statusCheck->motivo()}!";
        }

        $this->syncViewData($battle, $actor);
        $actorView = $this->findPokemonViewData($actor);

        if ($actorView === null) {
            $this->session->guardar($this->battleId, $battle);
            $this->processing = false;

            return;
        }

        $isPlayer = $actorView['team'] === 0;

        if ($isPlayer) {
            $this->currentMoves = array_map(
                fn (MovimientoBatalla $m) => DTOMovimientoBatalla::desdeDominio($m)->toLivewire(),
                $actor->pokemon()->moves()->all()
            );
            $this->phase = FaseCombate::SELECCION_OBJETIVO->value;
            $this->processing = false;
        } else {
            $this->processing = true;
            $this->prepareAiAnimation($battle, $actor);

            return; // la animación llama commitAction() via Alpine
        }

        $this->turnQueue = $this->buildTurnQueue($battle);
        $this->session->guardar($this->battleId, $battle);
    }

    /**
     * Finaliza una ronda (efectos de fin), inicia la siguiente
     * (acumular velocidad, efectos de inicio) y registra el marcador.
     */
    private function advanceRound(AgregadoBatalla $battle): void
    {
        if ($battle->turnManager()->round() > 0) {
            $battle->triggerRoundEndEffects();
        }
        $battle->turnManager()->startNewRound();
        $battle->triggerRoundStartEffects();
        $this->round = $battle->turnManager()->round();
        $this->log[] = "--- Ronda {$this->round} ---";
    }

    private function endBattle(AgregadoBatalla $battle): void
    {
        $this->phase = FaseCombate::BATALLA_TERMINADA->value;
        $winner = ! $battle->team1->todosDebilitados() ? $battle->team1->name : $battle->team2->name;
        $this->log[] = "¡{$winner} gana la batalla!";
        $this->resetAnimState();
        $this->syncViewData($battle);
        $this->session->guardar($this->battleId, $battle);
        $this->processing = false;

        $resultado = PresentadorResultadoBatalla::procesar($battle, $this->battleId, $this->session);
        $this->log = array_merge($this->log, $resultado['log']);
        $this->rewards = $resultado['rewards'];
        $this->habitatId = $resultado['habitatId'];
    }

    // ─── AI ──────────────────────────────────────────────────

    private function prepareAiAnimation(AgregadoBatalla $battle, Combatiente $actor): void
    {
        $objetivo = $battle->elegirObjetivoPara($actor);
        if ($objetivo === null) {
            return;
        }

        $movimiento = $battle->elegirMejorMovimiento($actor, $objetivo);
        if ($movimiento === null) {
            return;
        }

        // Movimientos autodirigidos (ej: Danza Espada) apuntan al actor
        $targetForAnim = $objetivo;
        $defenderId = $objetivo->id();
        if ($movimiento->tieneSelfStatChanges() && ! $movimiento->tieneTargetStatChanges()) {
            $defenderId = $actor->id();
            $targetForAnim = $actor;
        }

        $battle->setPendingAction(new DTOAccionBatalla(
            type: 'attack',
            actorId: $actor->id(),
            defenderId: $defenderId,
            attackerNombre: $actor->nombre(),
            move: DTOMovimientoBatalla::desdeDominio($movimiento),
        ));

        $this->setAnimState($actor, $targetForAnim, $movimiento);
        $this->syncViewData($battle, $actor);
        $this->session->guardar($this->battleId, $battle);
    }

    // ─── Ejecutar acción (compartido: jugador + IA) ──────────

    public function commitAction(): void
    {
        $battle = $this->session->cargar($this->battleId);
        if ($battle === null) {
            return;
        }

        $pending = $battle->pendingAction();
        if ($pending === null) {
            return;
        }

        $actor = $battle->team1->findCombatantById($pending->actorId)
            ?? $battle->team2->findCombatantById($pending->actorId);
        $objetivo = $battle->team1->findCombatantById($pending->defenderId)
            ?? $battle->team2->findCombatantById($pending->defenderId);

        if ($actor === null || $objetivo === null) {
            $battle->setPendingAction(null);
            $this->session->guardar($this->battleId, $battle);
            $this->resetAnimState();
            $this->nextActor();

            return;
        }

        $movimiento = $pending->move->toDomain();

        // Movimientos autodirigidos (ej: Danza Espada) siempre apuntan al actor
        if ($movimiento->tieneSelfStatChanges() && ! $movimiento->tieneTargetStatChanges()) {
            $objetivo = $actor;
        }

        $defenderTeam = $this->defenderTeam($battle, $objetivo);

        // 1. Calcular y aplicar daño (servicio compartido)
        $servicio = $this->servicioEjecucion ?? new ServicioEjecucionBatalla($battle->damageChain());

        $accion = new AccionBatalla(
            attacker: $actor,
            defender: $objetivo,
            move: $movimiento,
            defenderTeamHasVanguard: $defenderTeam->tieneVanguardiaViva(),
            weather: $battle->weather(),
        );

        $resultado = $servicio->calcularYAplicarDano($accion);

        $daño = $resultado->dano;
        $directPct = $resultado->directPct;

        // 2. Log de daño
        $isAi = $battle->team2->findCombatantById($pending->actorId) !== null;
        $prefix = $isAi ? 'RIVAL: ' : '';
        $this->log[] = $prefix.$servicio->generarLogMovimiento(
            $actor,
            $objetivo,
            $movimiento,
            $daño,
            $directPct,
            $defenderTeam->tieneVanguardiaViva(),
        );

        // 3. Aplicar estado y cambios de estadísticas
        $servicio->aplicarEstado($objetivo, $movimiento);
        if ($movimiento->tieneStatus() && $objetivo->estaVivo()) {
            $label = $movimiento->statusEffect->label();
            $this->log[] = "{$objetivo->nombre()} sufre {$label}!";
        }
        $this->applyMoveStatChanges($actor, $movimiento, true);
        $this->applyMoveStatChanges($objetivo, $movimiento, false);

        // 4. Disparar eventos de efectos (items, habilidades, etc.)
        $battle->subject()->notifyDamaged($objetivo, $daño);
        $objetivo->dispararDanioRecibido($daño, $battle);
        $actor->dispararDanioInfligido($objetivo, $daño, $battle);
        $battle->turnManager()->consumeAction($actor);

        // 5. Debilitamiento
        if (! $objetivo->estaVivo()) {
            $this->log[] = "¡{$objetivo->nombre()} se ha debilitado!";
            $battle->subject()->notifyFainted($objetivo);
        }
        if (! $actor->estaVivo()) {
            $this->log[] = "¡{$actor->nombre()} se ha debilitado!";
            $battle->subject()->notifyFainted($actor);
        }

        // 6. Limpiar estado pendiente y seguir
        $battle->setPendingAction(null);
        $this->resetAnimState();
        $this->syncViewData($battle, $actor);
        $this->session->guardar($this->battleId, $battle);
        $this->nextActor();
    }

    // ─── Interacciones del jugador ───────────────────────────

    public function previewTarget(int $teamIdx, int $pokemonIdx): void
    {
        $battle = $this->session->cargar($this->battleId);
        if ($battle === null) {
            return;
        }

        $actor = $this->resolveActor($battle);
        if ($actor === null) {
            return;
        }

        $target = $this->getTargetFromSelection($battle, $teamIdx, $pokemonIdx);
        if ($target === null) {
            return;
        }

        $this->selectedTargetTeam = $teamIdx;
        $this->selectedTargetIdx = $pokemonIdx;
        $this->selectedTargetRefId = $target->id();

        $this->currentMoves = MovesPreviewPresenter::para($actor, $battle, $target);
        $this->phase = FaseCombate::SELECCION_MOVIMIENTO->value;
        $this->session->guardar($this->battleId, $battle);
    }

    public function selectMove(int $index): void
    {
        $battle = $this->session->cargar($this->battleId);
        if ($battle === null) {
            return;
        }

        $actor = $this->resolveActor($battle);
        if ($actor === null) {
            return;
        }

        $move = $actor->pokemon()->moves()->get($index);
        if (! $move instanceof MovimientoBatalla) {
            return;
        }

        // Movimientos autodirigidos (ej: Danza Espada) apuntan al actor y no requieren objetivo enemigo
        $selfTargeting = $move->tieneSelfStatChanges() && ! $move->tieneTargetStatChanges();
        $defender = $selfTargeting
            ? $actor
            : $this->getTargetFromSelection($battle, $this->selectedTargetTeam, $this->selectedTargetIdx);

        if ($defender === null) {
            return;
        }

        $battle->setPendingAction(new DTOAccionBatalla(
            type: 'attack',
            actorId: $actor->id(),
            defenderId: $defender->id(),
            attackerNombre: $actor->nombre(),
            move: DTOMovimientoBatalla::desdeDominio($move),
        ));

        $this->selectedMoveIdx = null;
        $this->setAnimState($actor, $defender, $move);
        $this->syncViewData($battle, $actor);
        $this->session->guardar($this->battleId, $battle);
    }

    public function cancelTarget(): void
    {
        $this->selectedTargetTeam = null;
        $this->selectedTargetIdx = null;
        $this->selectedTargetRefId = '';
        $this->phase = FaseCombate::SELECCION_OBJETIVO->value;
    }

    // ─── Helpers ─────────────────────────────────────────────

    /**
     * Resuelve el combatiente activo a partir de actingRefId.
     */
    private function resolveActor(AgregadoBatalla $battle): ?Combatiente
    {
        return $battle->team1->findCombatantById($this->actingRefId)
            ?? $battle->team2->findCombatantById($this->actingRefId);
    }

    private function getTargetFromSelection(AgregadoBatalla $battle, ?int $teamIdx, ?int $pokemonIdx): ?Combatiente
    {
        if ($teamIdx === null || $pokemonIdx === null) {
            return null;
        }

        $team = $teamIdx === 0 ? $battle->team1 : $battle->team2;

        return $team->combatants()[$pokemonIdx] ?? null;
    }

    private function defenderTeam(AgregadoBatalla $battle, Combatiente $defender): EquipoBatalla
    {
        return $battle->team1->findCombatant($defender) !== null
            ? $battle->team1
            : $battle->team2;
    }

    /**
     * Aplica los cambios de estadísticas de un movimiento al combatiente correspondiente.
     */
    private function applyMoveStatChanges(Combatiente $combatant, MovimientoBatalla $move, bool $isActor): void
    {
        $changes = $isActor ? $move->selfStatChanges : $move->targetStatChanges;
        if ($changes->isEmpty()) {
            return;
        }

        foreach ($changes as $cambio) {
            $label = $cambio->stat->label();
            $combatant->setMultiplicadores($cambio->aplicadoEn($combatant->multiplicadores()));
            $verb = $cambio->factor > 1.0 ? 'subió' : 'bajó';
            $porcentaje = (int) round(abs($cambio->porcentaje()));
            $this->log[] = "{$combatant->nombre()} {$verb} {$label} en {$porcentaje}%!";
        }
    }

    private function findPokemonViewData(Combatiente $target): ?array
    {
        foreach ($this->team1 as $i => $p) {
            if ($p['refId'] === $target->id()) {
                return ['team' => 0, 'index' => $i];
            }
        }
        foreach ($this->team2 as $i => $p) {
            if ($p['refId'] === $target->id()) {
                return ['team' => 1, 'index' => $i];
            }
        }

        return null;
    }

    private function buildTurnQueue(AgregadoBatalla $battle): array
    {
        $alive = $battle->turnManager()->combatientesVivos()->all();

        usort(
            $alive,
            fn (Combatiente $a, Combatiente $b) => $b->velocidadAcumulada() <=> $a->velocidadAcumulada()
        );

        return array_map(fn (Combatiente $c) => $this->findPokemonViewData($c) ?? ['team' => 0, 'index' => 0], $alive);
    }

    // ─── Animación ───────────────────────────────────────────

    private function setAnimState(Combatiente $actor, Combatiente $target, MovimientoBatalla $move): void
    {
        $this->animTick++;
        $this->animAttackerId = $actor->id();
        $this->animAttackerNombre = $actor->nombre();
        // Movimientos autodirigidos (ej: Danza Espada) no necesitan parpadeo en el defensor
        if ($target->id() !== $actor->id()) {
            $this->animDefenderId = $target->id();
            $this->animDefenderNombre = $target->nombre();
        } else {
            $this->animDefenderId = '';
            $this->animDefenderNombre = '';
        }
    }

    private function resetAnimState(): void
    {
        $this->animAttackerId = '';
        $this->animDefenderId = '';
        $this->animAttackerNombre = '';
        $this->animDefenderNombre = '';
        $this->animMoveNombre = '';
        $this->selectedTargetTeam = null;
        $this->selectedTargetIdx = null;
        $this->selectedTargetRefId = '';
    }

    // ─── Sincronizar vista ───────────────────────────────────

    private function syncViewData(AgregadoBatalla $battle, ?Combatiente $actor = null): void
    {
        $this->weather = $battle->weather()->value;
        $this->log = array_merge($this->log, $battle->log()->entries());
        $battle->limpiarLog();

        $this->team1 = array_map(
            fn (Combatiente $c) => $c->aArrayVista(0),
            $battle->team1->combatants()
        );

        $this->team2 = array_map(
            fn (Combatiente $c) => $c->aArrayVista(1),
            $battle->team2->combatants()
        );

        if ($actor === null || ! $battle->team1->findCombatant($actor)) {
            return;
        }

        $canHitRetaguardia = ! $actor->estaEnVanguardia() || ! $battle->team2->tieneVanguardiaViva();

        foreach ($this->team2 as &$p) {
            $p['canTarget'] = $p['posicion'] !== 'retaguardia' || $canHitRetaguardia;
        }
        unset($p);
    }
}
