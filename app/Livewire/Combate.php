<?php

declare(strict_types=1);

namespace App\Livewire;

use Livewire\Component;
use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\FabricaBatallaInterface;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\ServicioEjecucionBatalla;
use Src\Battle\Presentation\DTOAccionBatalla;
use Src\Battle\Presentation\DTOMovimientoBatalla;
use Src\CombateEntrenadores\App\RegistrarResultadoEntrenador;

class Combate extends Component
{
    public string $battleId = '';

    public array $team1 = [];

    public array $team2 = [];

    public array $turnQueue = [];

    public array $currentMoves = [];

    public ?int $selectedMoveIdx = null;

    public string $phase = 'init';

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

    public ?int $selectedTargetTeam = null;

    public ?int $selectedTargetIdx = null;

    public string $selectedTargetRefId = '';

    private FabricaBatallaInterface $fabricaBatalla;

    private ?ServicioEjecucionBatalla $servicioEjecucion = null;

    // ─── Lifecycle ───────────────────────────────────────────

    public function nuevaBatalla(): void
    {
        $this->battleId = 'battle_'.uniqid();
        $this->initMockBattle();
    }

    public function mount(): void
    {
        $this->fabricaBatalla = app(FabricaBatallaInterface::class);

        $battleId = request()->query('battle_id');
        if (is_string($battleId) && $battleId !== '' && session()->has($battleId)) {
            $this->battleId = $battleId;
            $battle = $this->getBattle();
            if ($battle !== null) {
                $this->servicioEjecucion = new ServicioEjecucionBatalla($battle->damageChain());
                $this->syncViewData($battle);
                $this->log[] = '¡Comienza la batalla!';
                $this->saveBattle($battle);
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

    // ─── Persistencia (sesión) ───────────────────────────────

    private const SESSION_VERSION = 5;

    private function getBattle(): ?AgregadoBatalla
    {
        $data = session($this->battleId);
        if ($data === null) {
            return null;
        }

        // Formato: "v{version}|{serialized}"
        if (! str_contains($data, '|')) {
            session()->forget($this->battleId);

            return null;
        }

        [$version, $payload] = explode('|', $data, 2);

        try {
            /** @var AgregadoBatalla $battle */
            $battle = unserialize($payload);
        } catch (\Throwable $e) {
            // Versión antigua incompatible, limpiar sesión
            session()->forget($this->battleId);

            return null;
        }

        if (! $battle instanceof AgregadoBatalla) {
            session()->forget($this->battleId);

            return null;
        }

        return $battle;
    }

    private function saveBattle(AgregadoBatalla $battle): void
    {
        $payload = self::SESSION_VERSION.'|'.serialize($battle);
        session()->put($this->battleId, $payload);
    }

    // ─── Inicialización ──────────────────────────────────────

    private function initMockBattle(): void
    {
        $battle = $this->fabricaBatalla->createBattle();
        $this->servicioEjecucion = new ServicioEjecucionBatalla($battle->damageChain());
        $this->saveBattle($battle);

        $this->syncViewData($battle);
        $this->saveBattle($battle); // re-save after clearing log
        $this->log[] = '¡Comienza la batalla!';
        $this->nextActor();
    }

    // ─── Ciclo de turno ──────────────────────────────────────

    public function startBattle(): void
    {
        $this->nextActor();
    }

    /**
     * Avanza al siguiente actor. Si la ronda terminó, inicia una nueva
     * y dispara efectos de fin/inicio de ronda.
     */
    public function nextActor(): void
    {
        $battle = $this->getBattle();
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
        if (! $statusCheck['canAct']) {
            $this->log[] = "{$actor->nombre()} {$statusCheck['reason']}!";
            if ($statusCheck['selfDamage'] > 0) {
                $this->log[] = "¡{$actor->nombre()} se golpeó a sí mismo! ({$statusCheck['selfDamage']} daño)";
            }
            $battle->turnManager()->consumeAction($actor);
            $this->syncViewData($battle);
            $this->saveBattle($battle);
            $this->nextActor();

            return;
        }
        if ($statusCheck['reason'] === 'despertó' || $statusCheck['reason'] === 'se descongeló') {
            $this->log[] = "¡{$actor->nombre()} {$statusCheck['reason']}!";
        }

        $this->syncViewData($battle, $actor);
        $actorView = $this->findPokemonViewData($actor);

        if ($actorView === null) {
            $this->saveBattle($battle);
            $this->processing = false;

            return;
        }

        $isPlayer = $actorView['team'] === 0;

        if ($isPlayer) {
            $this->currentMoves = array_map(
                fn (MovimientoBatalla $m) => DTOMovimientoBatalla::desdeDominio($m)->toLivewire(),
                $actor->pokemon()->moves()->all()
            );
            $this->phase = 'player_target';
            $this->processing = false;
        } else {
            $this->processing = true;
            $this->prepareAiAnimation($battle, $actor);

            return; // la animación llama commitAction() via Alpine
        }

        $this->turnQueue = $this->buildTurnQueue($battle);
        $this->saveBattle($battle);
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
        $this->phase = 'battle_over';
        $winner = ! $battle->team1->todosDebilitados() ? $battle->team1->name : $battle->team2->name;
        $this->log[] = "¡{$winner} gana la batalla!";
        $this->resetAnimState();
        $this->syncViewData($battle);
        $this->saveBattle($battle);
        $this->processing = false;
        $this->registrarResultadoEntrenador($battle);
    }

    private function registrarResultadoEntrenador(AgregadoBatalla $battle): void
    {
        $meta = session($this->battleId.'_meta');
        if (! is_array($meta)) {
            return;
        }

        $won = ! $battle->team1->todosDebilitados();

        app(RegistrarResultadoEntrenador::class)->registrar(
            habitatId: (int) ($meta['habitat_id'] ?? 0),
            nivel: (int) ($meta['nivel'] ?? 0),
            trainerIndex: (int) ($meta['trainer_index'] ?? 0),
            userId: (int) ($meta['user_id'] ?? 0),
            fecha: (string) ($meta['fecha'] ?? today()->toDateString()),
            won: $won,
        );

        session()->forget($this->battleId.'_meta');
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
        $this->saveBattle($battle);
    }

    // ─── Ejecutar acción (compartido: jugador + IA) ──────────

    public function commitAction(): void
    {
        $battle = $this->getBattle();
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
            $this->saveBattle($battle);
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
            fromPosition: $actor->posicion(),
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
        $this->saveBattle($battle);
        $this->nextActor();
    }

    // ─── Interacciones del jugador ───────────────────────────

    public function previewTarget(int $teamIdx, int $pokemonIdx): void
    {
        $battle = $this->getBattle();
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

        $previews = [];
        foreach ($actor->pokemon()->moves() as $move) {
            $defenderTeam = $this->defenderTeam($battle, $target);

            $action = new AccionBatalla(
                attacker: $actor,
                defender: $target,
                move: $move,
                fromPosition: $actor->posicion(),
                defenderTeamHasVanguard: $defenderTeam->tieneVanguardiaViva(),
                weather: $battle->weather(),
                isPreview: true,
            );

            $previews[] = [
                'nombre' => $move->nombre,
                'tipo' => $move->tipo->value,
                'potencia' => $move->potencia,
                'categoria' => $move->categoria->value,
                'daño' => $battle->damageChain()->calculate($action),
                'efectividad' => $move->tipo->effectiveness($target->pokemon()),
                'stab' => $this->tieneStab($actor, $move),
                'directo' => $actor->obtenerPorcentajeDanioDirecto() > 0,
                'statusEffect' => $move->statusEffect->value,
                'selfStatChanges' => $move->selfStatChanges,
                'targetStatChanges' => $move->targetStatChanges,
            ];
        }

        $this->currentMoves = $previews;
        $this->phase = 'player_move';
        $this->saveBattle($battle);
    }

    public function selectMove(int $index): void
    {
        $battle = $this->getBattle();
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
        $this->saveBattle($battle);
    }

    public function cancelTarget(): void
    {
        $this->selectedTargetTeam = null;
        $this->selectedTargetIdx = null;
        $this->selectedTargetRefId = '';
        $this->phase = 'player_target';
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

    private function tieneStab(Combatiente $actor, MovimientoBatalla $move): bool
    {
        foreach ($actor->pokemon()->tiposCollection() as $tipo) {
            if ($tipo === $move->tipo) {
                return true;
            }
        }

        return false;
    }

    /**
     * Aplica los cambios de estadísticas de un movimiento al combatiente correspondiente.
     */
    private function applyMoveStatChanges(Combatiente $combatant, MovimientoBatalla $move, bool $isActor): void
    {
        $changes = $isActor ? $move->selfStatChanges : $move->targetStatChanges;
        if (empty($changes)) {
            return;
        }

        $statLabels = [
            'attack' => 'Ataque', 'defense' => 'Defensa',
            'spAtk' => 'At. Especial', 'spDef' => 'Def. Especial',
            'speed' => 'Velocidad', 'accuracy' => 'Precisión', 'evasion' => 'Evasión',
        ];

        foreach ($changes as $change) {
            $combatant->aplicarCambioEtapa($change['stat'], $change['stages']);
            $label = $statLabels[$change['stat']] ?? $change['stat'];
            $verb = $change['stages'] > 0 ? 'subió' : 'bajó';
            $stages = abs($change['stages']);
            $this->log[] = "{$combatant->nombre()} {$verb} {$label} en {$stages}!";
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
        $alive = $battle->turnManager()->combatientesVivos();

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
        $this->log = array_merge($this->log, $battle->log());
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
