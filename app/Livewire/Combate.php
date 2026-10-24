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
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\FaseCombate;
use Src\Battle\Domain\Enums\ObjetivoMovimiento;
use Src\Battle\Domain\Enums\PosturaCombate;
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

    /** @var list<array<string, mixed>> */
    public array $eventos = [];

    public int $eventoTick = 0;

    public ?int $selectedTargetTeam = null;

    public ?int $selectedTargetIdx = null;

    public string $selectedTargetRefId = '';

    /** @var array<string, string> refId => índice del movimiento prioritario elegido */
    public array $prioridades = [];

    /** @var array<string, list<array{index: int, nombre: string, prioridad: int}>> */
    public array $prioridadesDisponibles = [];

    private FabricaBatallaInterface $fabricaBatalla;

    private BattleSessionService $session;

    private ?ServicioEjecucionBatalla $servicioEjecucion = null;

    private bool $grabandoEventos = false;

    private ?string $forzarSiguienteActorId = null;

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

        $turnos = $battle->turnManager();

        if (! $turnos->bothTeamsAlive()) {
            $this->endBattle($battle);

            return;
        }

        // Al inicio (ronda 0) o si todos actuaron, avanzar ronda
        if ($turnos->round() === 0 || ! $turnos->hayAlgunoConAccionPendiente()) {
            if ($this->advanceRoundOrEndBattle($battle)) {
                return;
            }
        }

        if ($battle->rondaPreparada() !== $turnos->round()) {
            $this->abrirPreparacion($battle);

            if ($this->prioridadesDisponibles === []) {
                $this->confirmarPreparacion();
            }

            return;
        }

        $actor = $turnos->getNextActor();

        // Sin actor pendiente (caso defensivo): forzar nueva ronda
        if ($actor === null) {
            if ($this->advanceRoundOrEndBattle($battle)) {
                return;
            }

            $actor = $turnos->getNextActor();
            if ($actor === null) {
                $this->endBattle($battle);

                return;
            }
        }

        if ($this->forzarSiguienteActorId !== null) {
            $forzado = $battle->team1->findCombatantById($this->forzarSiguienteActorId);
            $this->forzarSiguienteActorId = null;

            if ($forzado !== null && $forzado->estaVivo() && $forzado->tieneAccionPendienteEstaRonda()) {
                $actor = $forzado;
            }
        }

        $this->actingRefId = $actor->id();

        // Verificar si el actor puede actuar (sueño, hielo, parálisis, confusión)
        $antesEstado = $this->grabandoEventos ? $this->capturarEstado($battle) : [];
        $statusCheck = $actor->puedeActuar();
        if ($this->grabandoEventos) {
            $motivo = $statusCheck->autoDanio() > 0
                ? "{$actor->nombre()} se golpeó a sí mismo ({$statusCheck->autoDanio()} daño)"
                : "{$actor->nombre()} {$statusCheck->motivo()}";

            $this->registrarEvento(
                $statusCheck->esPermitida() ? 'status' : 'status_block',
                $antesEstado,
                $this->capturarEstado($battle),
                [
                    'actorId' => $actor->id(),
                    'texto' => $motivo,
                    'forzar' => ! $statusCheck->esPermitida(),
                ],
            );
        }
        if (! $statusCheck->esPermitida()) {
            $this->log[] = "{$actor->nombre()} {$statusCheck->motivo()}!";
            if ($statusCheck->autoDanio() > 0) {
                $this->log[] = "¡{$actor->nombre()} se golpeó a sí mismo! ({$statusCheck->autoDanio()} daño)";
            }
            $turnos->consumeAction($actor);
            $this->syncViewData($battle);
            $this->session->guardar($this->battleId, $battle);
            $this->nextActor();

            return;
        }
        if ($statusCheck->motivo() === 'despertó' || $statusCheck->motivo() === 'se descongeló') {
            $this->log[] = "¡{$actor->nombre()} {$statusCheck->motivo()}!";
        }

        if ($actor->postura() !== PosturaCombate::ATACAR) {
            $this->log[] = "{$actor->nombre()} mantiene la postura de {$actor->postura()->label()}.";
            $turnos->consumeAction($actor);
            $this->syncViewData($battle);
            $this->session->guardar($this->battleId, $battle);
            $this->nextActor();

            return;
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
            $this->turnQueue = $this->buildTurnQueue($battle);

            return; // la animación llama commitAction() via Alpine
        }

        $this->turnQueue = $this->buildTurnQueue($battle);
        $this->session->guardar($this->battleId, $battle);
    }

    /**
     * Finaliza una ronda (efectos de fin), inicia la siguiente
     * (resetear acciones consumidas, efectos de inicio) y registra el marcador.
     */
    private function advanceRound(AgregadoBatalla $battle): void
    {
        $rondaNueva = $battle->turnManager()->round() + 1;
        $previoFin = $this->grabandoEventos ? $this->capturarEstado($battle) : [];

        if ($battle->turnManager()->round() > 0) {
            $battle->triggerRoundEndEffects();
        }

        $battle->turnManager()->startNewRound();
        $battle->triggerRoundStartEffects();

        // Un único evento para cierre + inicio de ronda. Así Presión y
        // cualquier regeneración de inicio se muestran como un cambio neto y
        // no como “pierde escudo → lo recupera” en dos pasos.
        $this->registrarEvento('round', $previoFin, $this->capturarEstado($battle), [
            'ronda' => $rondaNueva,
            'forzar' => true,
        ]);

        $this->round = $battle->turnManager()->round();
        $this->log[] = "--- Ronda {$this->round} ---";
    }

    /**
     * Avanza a la ronda siguiente y cierra la batalla si los efectos de fin
     * de ronda (veneno/quemadura/clima) debilitaron al último pokémon de un
     * bando.
     *
     * @return bool true si la batalla terminó y no hay actor que preparar
     */
    private function advanceRoundOrEndBattle(AgregadoBatalla $battle): bool
    {
        $this->advanceRound($battle);

        if ($battle->turnManager()->bothTeamsAlive()) {
            return false;
        }

        $this->endBattle($battle);

        return true;
    }

    private function abrirPreparacion(AgregadoBatalla $battle): void
    {
        $this->phase = FaseCombate::PREPARACION->value;
        $this->prioridades = [];
        $this->prioridadesDisponibles = [];

        foreach ($battle->team1->combatientesCollection()->vivos() as $combatiente) {
            $this->prioridades[$combatiente->id()] = '';

            $disponibles = [];
            foreach ($combatiente->pokemon()->moves()->all() as $index => $movimiento) {
                if ($movimiento->priority <= 0 || ! $combatiente->movimientoDisponible($movimiento)) {
                    continue;
                }

                $disponibles[] = [
                    'index' => $index,
                    'nombre' => $movimiento->nombre,
                    'prioridad' => $movimiento->priority,
                ];
            }

            if ($disponibles !== []) {
                $this->prioridadesDisponibles[$combatiente->id()] = $disponibles;
            }
        }

        $this->syncViewData($battle);
        $this->turnQueue = $this->buildTurnQueue($battle);
        $this->resetAnimState();
        $this->session->guardar($this->battleId, $battle);
        $this->processing = false;
    }

    public function confirmarPreparacion(): void
    {
        $battle = $this->session->cargar($this->battleId);
        if ($battle === null || $this->phase !== FaseCombate::PREPARACION->value) {
            return;
        }

        $equipoJugador = $battle->team1;
        $vivosJugador = $equipoJugador->combatientesCollection()->vivos()->all();

        foreach ($vivosJugador as $combatiente) {
            $prioridad = null;
            $indicePrioridad = $this->prioridades[$combatiente->id()] ?? '';
            if ($indicePrioridad !== '') {
                $candidato = $combatiente->pokemon()->moves()->get((int) $indicePrioridad);
                if ($candidato instanceof MovimientoBatalla
                    && $candidato->priority > 0
                    && $combatiente->movimientoDisponible($candidato)) {
                    $prioridad = $candidato;
                }
            }

            if ($prioridad !== null) {
                // Usar una prioridad es una acción de ataque: anula Guardia/Cubrir.
                $combatiente->setPostura(PosturaCombate::ATACAR);
                $combatiente->setObjetivoCubiertoId('');
                $combatiente->declararPrioridad($prioridad);

                continue;
            }

            $combatiente->limpiarPrioridad();
        }

        $equipoJugador->normalizarCubridores();

        foreach ($battle->team2->combatientesCollection()->vivos() as $rival) {
            $rival->limpiarPrioridad();
        }

        $battle->team2->normalizarCubridores();
        $this->declararPrioridadesIA($battle);

        $battle->setRondaPreparada($battle->turnManager()->round());
        $this->log[] = "--- Ronda {$battle->turnManager()->round()}: preparación confirmada ---";

        $this->phase = FaseCombate::INICIO->value;
        $this->syncViewData($battle);
        $this->resolverPrioridades($battle);
        $this->prioridades = [];
        $this->prioridadesDisponibles = [];
        $this->session->guardar($this->battleId, $battle);
        $this->nextActor();
    }

    public function saltarPreparacion(): void
    {
        $this->prioridades = [];
        $this->confirmarPreparacion();
    }

    /**
     * La IA declara, para cada rival vivo, su mejor movimiento con prioridad.
     * Siempre puede usarla, aunque el jugador decida saltar la preparación.
     */
    private function declararPrioridadesIA(AgregadoBatalla $battle): void
    {
        foreach ($battle->team2->combatientesCollection()->vivos() as $rival) {
            $mejor = null;

            foreach ($rival->pokemon()->moves()->all() as $movimiento) {
                if ($movimiento->priority <= 0 || ! $rival->movimientoDisponible($movimiento)) {
                    continue;
                }

                if ($mejor === null
                    || $movimiento->priority > $mejor->priority
                    || ($movimiento->priority === $mejor->priority && $movimiento->potencia > $mejor->potencia)) {
                    $mejor = $movimiento;
                }
            }

            if ($mejor !== null) {
                $rival->setPostura(PosturaCombate::ATACAR);
                $rival->setObjetivoCubiertoId('');
                $rival->declararPrioridad($mejor);
            }
        }
    }

    /**
     * Resuelve todas las acciones de prioridad antes de las acciones normales.
     * Orden: bracket de prioridad (mayor primero) y, dentro del bracket, la
     * velocidad efectiva (Trick Room invierte el desempate).
     */
    private function resolverPrioridades(AgregadoBatalla $battle): void
    {
        $declaradas = [];
        foreach ($battle->turnManager()->combatientesVivos() as $combatiente) {
            $movimiento = $combatiente->prioridadDeclarada();
            if ($movimiento !== null) {
                $declaradas[] = ['actor' => $combatiente, 'movimiento' => $movimiento];
            }
        }

        if ($declaradas === []) {
            return;
        }

        $turnos = $battle->turnManager();
        $invertir = $battle->trickRoomActivo();
        usort($declaradas, function (array $a, array $b) use ($turnos, $invertir): int {
            $porPrioridad = $b['movimiento']->priority <=> $a['movimiento']->priority;
            if ($porPrioridad !== 0) {
                return $porPrioridad;
            }

            $va = $turnos->velocidadEfectivaDe($a['actor']);
            $vb = $turnos->velocidadEfectivaDe($b['actor']);

            return $invertir ? $va <=> $vb : $vb <=> $va;
        });

        $servicio = $this->servicioEjecucion ?? new ServicioEjecucionBatalla($battle->damageChain());

        foreach ($declaradas as $declarada) {
            /** @var Combatiente $actor */
            $actor = $declarada['actor'];
            /** @var MovimientoBatalla $movimiento */
            $movimiento = $declarada['movimiento'];
            $actor->limpiarPrioridad();

            if (! $battle->turnManager()->bothTeamsAlive()) {
                break;
            }

            if (! $actor->estaVivo()) {
                continue;
            }

            if (! $actor->movimientoDisponible($movimiento)) {
                $actor->romperCombo();
                $this->log[] = "{$actor->nombre()} no puede usar {$movimiento->nombre} todavía.";
                $turnos->consumeAction($actor);

                continue;
            }

            $puedeActuar = $actor->puedeActuar();
            if (! $puedeActuar->esPermitida()) {
                $this->log[] = "{$actor->nombre()} no puede usar {$movimiento->nombre}: {$puedeActuar->motivo()}";
                if ($puedeActuar->autoDanio() > 0) {
                    $this->log[] = "¡{$actor->nombre()} se golpeó a sí mismo! ({$puedeActuar->autoDanio()} daño)";
                }
                $turnos->consumeAction($actor);

                continue;
            }

            $objetivo = $this->objetivoParaPrioridad($battle, $actor, $movimiento);
            if ($objetivo === null) {
                $actor->romperCombo();
                $this->log[] = "{$actor->nombre()} no encuentra objetivo para {$movimiento->nombre}.";
                $turnos->consumeAction($actor);

                continue;
            }

            $defenderTeam = $this->defenderTeam($battle, $objetivo);
            $accion = new AccionBatalla(
                attacker: $actor,
                defender: $objetivo,
                move: $movimiento,
                defenderTeamHasVanguard: $defenderTeam->tieneVanguardiaViva(),
                weather: $battle->weather(),
                terreno: $battle->terreno(),
            );

            $actor->effects()->dispararInicioTurno($actor, $battle);
            $resultado = $servicio->calcularYAplicarDano($accion, $battle);
            $isAi = $battle->team2->findCombatantById($actor->id()) !== null;
            $this->log[] = ($isAi ? 'RIVAL PRIORIDAD: ' : 'PRIORIDAD: ').$servicio->generarLogMovimiento(
                $actor,
                $objetivo,
                $movimiento,
                $resultado->dano,
                $resultado->directPct,
                $defenderTeam->tieneVanguardiaViva(),
            );

            $objetivos = $resultado->objetivos;
            foreach ($objetivos as $target) {
                $servicio->aplicarEstado($target, $movimiento, $battle, $actor);
                if ($movimiento->tieneStatus() && $target->estaVivo()) {
                    $this->log[] = "{$target->nombre()} sufre {$movimiento->statusEffect->label()}!";
                }
            }

            $this->applyMoveStatChanges($actor, $movimiento, true);
            foreach ($objetivos as $target) {
                $this->applyMoveStatChanges($target, $movimiento, false);
            }

            $battle->subject()->notifyDamaged($objetivo, $resultado->dano);
            $objetivo->dispararDanioRecibido($resultado->dano, $battle);
            $actor->dispararDanioInfligido($objetivo, $resultado->dano, $battle);
            $turnos->consumeAction($actor);
            $actor->consumirMovimiento($movimiento);
            $actor->effects()->dispararFinTurno($actor, $battle);

            if (! $objetivo->estaVivo()) {
                $this->log[] = "¡{$objetivo->nombre()} se ha debilitado!";
            }

            $nuevosDebilitados = $battle->procesarDebilitamientos();
            if ($nuevosDebilitados > 0 && $actor->estaVivo()) {
                $actor->otorgarAccionExtra();
            }

            if (! $actor->estaVivo()) {
                $this->log[] = "¡{$actor->nombre()} se ha debilitado!";
            }
        }

        $this->syncViewData($battle);
    }

    private function objetivoParaPrioridad(
        AgregadoBatalla $battle,
        Combatiente $actor,
        MovimientoBatalla $movimiento,
    ): ?Combatiente {
        $autodirigido = in_array($movimiento->objetivo, [
            ObjetivoMovimiento::SELF,
            ObjetivoMovimiento::ALLY_SIDE,
            ObjetivoMovimiento::ALLIES,
            ObjetivoMovimiento::ALLY_TEAM,
        ], true) || ($movimiento->tieneSelfStatChanges() && ! $movimiento->tieneTargetStatChanges());

        if ($autodirigido || $movimiento->objetivo === ObjetivoMovimiento::ALL) {
            return $actor;
        }

        return $battle->elegirObjetivoPara($actor);
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

        if ($this->grabandoEventos) {
            $this->eventos[] = [
                'id' => $this->eventoTick.'.'.(count($this->eventos) + 1),
                'tipo' => 'battle_over',
                'cambios' => [],
                'muertos' => [],
                'victoria' => ! $battle->team1->todosDebilitados(),
                'texto' => "¡{$winner} gana la batalla!",
            ];
        }
    }

    // ─── AI ──────────────────────────────────────────────────

    private function prepareAiAnimation(AgregadoBatalla $battle, Combatiente $actor): void
    {
        $objetivo = $battle->elegirObjetivoPara($actor);
        if ($objetivo === null) {
            return;
        }

        $movimiento = $battle->elegirMejorMovimiento($actor, $objetivo);
        if ($movimiento === null || ! $actor->movimientoDisponible($movimiento)) {
            $this->log[] = "{$actor->nombre()} no tiene movimientos disponibles.";
            $battle->turnManager()->consumeAction($actor);
            $this->syncViewData($battle, $actor);
            $this->session->guardar($this->battleId, $battle);
            $this->nextActor();

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

        $this->iniciarEventos();

        try {
            $this->resolverAccion($battle);
        } finally {
            $this->cerrarEventos($battle);
        }
    }

    private function resolverAccion(AgregadoBatalla $battle): void
    {
        $pending = $battle->pendingAction();
        if ($pending === null) {
            $this->resetAnimState();
            $this->processing = false;

            return;
        }

        $actor = $battle->team1->findCombatantById($pending->actorId)
            ?? $battle->team2->findCombatantById($pending->actorId);
        $objetivo = $battle->team1->findCombatantById($pending->defenderId)
            ?? $battle->team2->findCombatantById($pending->defenderId);

        if ($actor === null || $objetivo === null) {
            $actor?->romperCombo();
            $battle->setPendingAction(null);
            $this->session->guardar($this->battleId, $battle);
            $this->resetAnimState();
            $this->nextActor();

            return;
        }

        $movimiento = $pending->move->toDomain();

        if (! $actor->movimientoDisponible($movimiento)) {
            $actor->romperCombo();
            $this->log[] = "{$actor->nombre()} ya no puede usar {$movimiento->nombre}.";
            $battle->setPendingAction(null);
            $battle->turnManager()->consumeAction($actor);
            $this->resetAnimState();
            $this->syncViewData($battle, $actor);
            $this->session->guardar($this->battleId, $battle);
            $this->nextActor();

            return;
        }

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
            terreno: $battle->terreno(),
        );

        $previo = $this->capturarEstado($battle);
        $actor->effects()->dispararInicioTurno($actor, $battle);
        $resultado = $servicio->calcularYAplicarDano($accion, $battle);
        $actual = $this->capturarEstado($battle);

        $this->registrarEvento('damage', $previo, $actual, [
            'actorId' => $actor->id(),
            'objetivoId' => $objetivo->id(),
            'movimiento' => $movimiento->nombre,
        ]);
        $previo = $actual;

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

        // 3. Aplicar estado y cambios de estadísticas a todos los objetivos
        $objetivos = $resultado->objetivos;
        foreach ($objetivos as $target) {
            $servicio->aplicarEstado($target, $movimiento, $battle, $actor);
            if ($movimiento->tieneStatus() && $target->estaVivo()) {
                $label = $movimiento->statusEffect->label();
                $this->log[] = "{$target->nombre()} sufre {$label}!";
            }
        }

        $actual = $this->capturarEstado($battle);
        $this->registrarEvento('status', $previo, $actual, [
            'actorId' => $actor->id(),
            'objetivoId' => $objetivo->id(),
            'texto' => $movimiento->tieneStatus()
                ? "{$objetivo->nombre()} sufre {$movimiento->statusEffect->label()}"
                : '',
            'forzar' => $movimiento->tieneStatus() && $objetivo->estaVivo(),
        ]);
        $previo = $actual;

        $this->applyMoveStatChanges($actor, $movimiento, true);
        foreach ($objetivos as $target) {
            $this->applyMoveStatChanges($target, $movimiento, false);
        }

        // 4. Disparar eventos de efectos (items, habilidades, etc.)
        $battle->subject()->notifyDamaged($objetivo, $daño);
        $objetivo->dispararDanioRecibido($daño, $battle);
        $actor->dispararDanioInfligido($objetivo, $daño, $battle);
        $this->registrarEvento('effect', $previo, $this->capturarEstado($battle), [
            'actorId' => $actor->id(),
            'objetivoId' => $objetivo->id(),
            'texto' => 'Efecto de combate',
        ]);
        $battle->turnManager()->consumeAction($actor);
        $actor->consumirMovimiento($movimiento);
        $actor->effects()->dispararFinTurno($actor, $battle);

        // 5. Debilitamiento (incluye objetivos secundarios, cubridores y vínculos)
        if (! $objetivo->estaVivo()) {
            $this->log[] = "¡{$objetivo->nombre()} se ha debilitado!";
        }

        $nuevosDebilitados = $battle->procesarDebilitamientos();
        if ($nuevosDebilitados > 0 && $actor->estaVivo()) {
            $actor->otorgarAccionExtra();
        }

        if (! $actor->estaVivo()) {
            $this->log[] = "¡{$actor->nombre()} se ha debilitado!";
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
        if ($target === null || ! $target->estaVivo()) {
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

        if (! $actor->movimientoDisponible($move)) {
            $this->log[] = match ($actor->motivoMovimientoNoDisponible($move)) {
                'cargando' => "{$move->nombre} aún se está cargando ({$actor->cargaMovimiento($move)}/{$move->perfil->cargaMaxima}).",
                'sin_usos' => "{$move->nombre} no tiene usos restantes.",
                'silencio' => "{$actor->nombre()} está en silencio y no puede usar movimientos de estado.",
                'objeto_eleccion' => "{$move->nombre} está bloqueado por el objeto de elección.",
                default => "{$move->nombre} no está disponible.",
            };

            return;
        }

        // Movimientos autodirigidos (ej: Danza Espada) apuntan al actor y no requieren objetivo enemigo
        $selfTargeting = in_array($move->objetivo, [
            ObjetivoMovimiento::SELF,
            ObjetivoMovimiento::ALLY_SIDE,
            ObjetivoMovimiento::ALLIES,
            ObjetivoMovimiento::ALLY_TEAM,
        ], true) || ($move->tieneSelfStatChanges() && ! $move->tieneTargetStatChanges());
        $defender = $selfTargeting
            ? $actor
            : $this->getTargetFromSelection($battle, $this->selectedTargetTeam, $this->selectedTargetIdx);

        $desafio = $actor->condicion(ClaveCondicion::DESAFIO);
        if (! $selfTargeting && $desafio !== null && $desafio->objetivoForzadoId !== '') {
            $defender = $battle->team1->findCombatantById($desafio->objetivoForzadoId)
                ?? $battle->team2->findCombatantById($desafio->objetivoForzadoId)
                ?? $defender;
        }

        if ($defender === null || ! $defender->estaVivo()) {
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

    /**
     * Adelanta el turno del siguiente pokémon del jugador cuando la cola
     * muestra dos turnos propios consecutivos. El actor actual sigue pendiente
     * y actuará después; no se pierde ninguna acción.
     */
    public function swapTurno(): void
    {
        if (! in_array($this->phase, [
            FaseCombate::SELECCION_OBJETIVO->value,
            FaseCombate::SELECCION_MOVIMIENTO->value,
        ], true)) {
            return;
        }

        if ($this->processing || $this->animAttackerId !== '') {
            return;
        }

        $siguienteTurno = $this->turnQueue[1] ?? null;
        if (($this->turnQueue[0]['team'] ?? null) !== 0 || ($siguienteTurno['team'] ?? null) !== 0) {
            return;
        }

        $siguiente = $this->team1[$siguienteTurno['index']] ?? null;
        if ($siguiente === null || ! $siguiente['alive'] || $siguiente['refId'] === $this->actingRefId) {
            return;
        }

        $this->resetAnimState();
        $this->selectedMoveIdx = null;
        $this->forzarSiguienteActorId = $siguiente['refId'];
        $this->nextActor();
    }

    /**
     * Retrasa la acción del jugador actual al final de la ronda. Se puede
     * usar una vez por ronda y no consume la acción.
     */
    public function retrasarTurno(): void
    {
        if (! in_array($this->phase, [
            FaseCombate::SELECCION_OBJETIVO->value,
            FaseCombate::SELECCION_MOVIMIENTO->value,
        ], true)) {
            return;
        }

        if ($this->processing || $this->animAttackerId !== '') {
            return;
        }

        $battle = $this->session->cargar($this->battleId);
        if ($battle === null) {
            return;
        }

        $actor = $this->resolveActor($battle);
        if ($actor === null || ! $actor->retrasarTurno()) {
            return;
        }

        $this->log[] = "{$actor->nombre()} retrasa su turno.";
        $this->resetAnimState();
        $this->selectedMoveIdx = null;
        $this->session->guardar($this->battleId, $battle);
        $this->nextActor();
    }

    /**
     * Consume la acción sin atacar. Sirve cuando todos los movimientos están
     * cargando, silenciados o sin usos.
     */
    public function pasarTurno(): void
    {
        if (! in_array($this->phase, [
            FaseCombate::SELECCION_OBJETIVO->value,
            FaseCombate::SELECCION_MOVIMIENTO->value,
        ], true)) {
            return;
        }

        if ($this->processing || $this->animAttackerId !== '') {
            return;
        }

        $battle = $this->session->cargar($this->battleId);
        if ($battle === null) {
            return;
        }

        $actor = $this->resolveActor($battle);
        if ($actor === null) {
            return;
        }

        $this->log[] = "{$actor->nombre()} espera.";
        $battle->turnManager()->consumeAction($actor);
        $this->resetAnimState();
        $this->selectedMoveIdx = null;
        $this->syncViewData($battle, $actor);
        $this->session->guardar($this->battleId, $battle);
        $this->nextActor();
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
            $factor = $cambio->factorTemporal();
            $combatant->setMultiplicadores($cambio->aplicadoTemporalEn($combatant->multiplicadores()));
            $verb = $factor > 1.0 ? 'subió' : 'bajó';
            $porcentaje = (int) round(abs(($factor - 1.0) * 100));
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

    // ─── Eventos de animación ────────────────────────────────

    /**
     * Instantánea de los vitales y estados de todos los combatientes.
     *
     * @return array<string, array{hp: float, defHp: float, spDefHp: float, alive: bool, status: string, statusTurns: int}>
     */
    private function capturarEstado(AgregadoBatalla $battle): array
    {
        $estado = [];

        foreach ($battle->turnManager()->allCombatants() as $combatiente) {
            $estado[$combatiente->id()] = [
                'hp' => $combatiente->hpActual(),
                'defHp' => $combatiente->defensaHpActual(),
                'spDefHp' => $combatiente->defensaEspHpActual(),
                'alive' => $combatiente->estaVivo(),
                'status' => $combatiente->estado()->value,
                'statusTurns' => $combatiente->turnosEstado(),
            ];
        }

        return $estado;
    }

    private function iniciarEventos(): void
    {
        $this->eventos = [];
        $this->eventoTick++;
        $this->grabandoEventos = true;
    }

    private function cerrarEventos(AgregadoBatalla $battle): void
    {
        if ($this->grabandoEventos) {
            $this->eventos[] = [
                'id' => $this->eventoTick.'.'.(count($this->eventos) + 1),
                'tipo' => 'fin',
                'cambios' => [],
                'muertos' => [],
                'estado' => $this->capturarEstado($battle),
            ];
        }

        $this->grabandoEventos = false;
    }

    /**
     * Registra un evento con el delta de cada combatiente afectado. Los
     * eventos se reproducen en orden en el cliente (cola de animación).
     *
     * @param  array<string, array{hp: float, defHp: float, spDefHp: float, alive: bool, status: string, statusTurns: int}>  $antes
     * @param  array<string, array{hp: float, defHp: float, spDefHp: float, alive: bool, status: string, statusTurns: int}>  $despues
     * @param  array<string, mixed>  $extra
     */
    private function registrarEvento(string $tipo, array $antes, array $despues, array $extra = []): void
    {
        if (! $this->grabandoEventos) {
            return;
        }

        $cambios = [];
        $muertos = [];

        foreach ($despues as $refId => $actual) {
            $previo = $antes[$refId] ?? null;
            if ($previo === null) {
                continue;
            }

            $delta = [
                'hp' => round($actual['hp'] - $previo['hp'], 2),
                'def' => round($actual['defHp'] - $previo['defHp'], 2),
                'spdef' => round($actual['spDefHp'] - $previo['spDefHp'], 2),
            ];

            $cambioVida = abs($delta['hp']) > 0.001
                || abs($delta['def']) > 0.001
                || abs($delta['spdef']) > 0.001;
            $cambioEstado = $actual['status'] !== $previo['status']
                || $actual['statusTurns'] !== $previo['statusTurns'];

            if (! $cambioVida && ! $cambioEstado) {
                continue;
            }

            $rotas = [];
            if ($previo['defHp'] > 0 && $actual['defHp'] <= 0) {
                $rotas[] = 'def';
            }
            if ($previo['spDefHp'] > 0 && $actual['spDefHp'] <= 0) {
                $rotas[] = 'spdef';
            }

            $cambios[$refId] = [
                'previo' => $previo,
                'actual' => $actual,
                'delta' => $delta,
                'rotas' => $rotas,
            ];

            if ($previo['alive'] && ! $actual['alive']) {
                $muertos[] = $refId;
            }
        }

        $forzar = (bool) ($extra['forzar'] ?? false);
        unset($extra['forzar']);

        if ($cambios === [] && ! $forzar) {
            return;
        }

        $this->eventos[] = array_merge([
            'id' => $this->eventoTick.'.'.(count($this->eventos) + 1),
            'tipo' => $tipo,
            'cambios' => $cambios,
            'muertos' => $muertos,
        ], $extra);
    }

    /**
     * Cola de la ronda en curso: el actor actual siempre encabeza la lista
     * (puede haberse adelantado con swapTurno), el resto va en orden de
     * velocidad efectiva descendente (empates: orden de inserción).
     *
     * @return list<array{team: int, index: int}>
     */
    private function buildTurnQueue(AgregadoBatalla $battle): array
    {
        $pendientes = $battle->turnManager()->combatientesVivos()
            ->conAccionPendiente()
            ->all();

        $turnos = $battle->turnManager();
        $invertir = $battle->trickRoomActivo();

        usort($pendientes, function (Combatiente $a, Combatiente $b) use ($turnos, $invertir): int {
            if ($a->id() === $this->actingRefId) {
                return -1;
            }
            if ($b->id() === $this->actingRefId) {
                return 1;
            }

            if ($a->turnoRetrasado() !== $b->turnoRetrasado()) {
                return $a->turnoRetrasado() ? 1 : -1;
            }

            $va = $turnos->velocidadEfectivaDe($a);
            $vb = $turnos->velocidadEfectivaDe($b);

            return $invertir ? $va <=> $vb : $vb <=> $va;
        });

        return array_map(fn (Combatiente $c) => $this->findPokemonViewData($c) ?? ['team' => 0, 'index' => 0], $pendientes);
    }

    // ─── Animación ───────────────────────────────────────────

    private function setAnimState(Combatiente $actor, Combatiente $target, MovimientoBatalla $move): void
    {
        $this->animTick++;
        $this->animAttackerId = $actor->id();
        $this->animAttackerNombre = $actor->nombre();
        $this->animMoveNombre = $move->nombre;
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
