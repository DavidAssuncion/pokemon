<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Combate\Soporte\ColaTurnosBatalla;
use App\Livewire\Combate\Soporte\DTO\SnapshotBatalla;
use App\Livewire\Combate\Soporte\EstadoAnimacionBatalla;
use App\Livewire\Combate\Soporte\GrabadorEventosBatalla;
use App\Livewire\Combate\Soporte\ResolutorAccionBatalla;
use App\Livewire\Combate\Soporte\ResolutorPosicionCombatiente;
use App\Livewire\Combate\Soporte\SincronizadorVistaBatalla;
use App\Livewire\Presenters\BatallaVistaPresenter;
use App\Livewire\Presenters\MovesPreviewPresenter;
use App\Livewire\Presenters\PresentadorResultadoBatalla;
use App\Support\BattleSessionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Src\Battle\App\UsarObjetoEnCombate;
use Src\Battle\Domain\AccionObjeto;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\DataTransferObjects\DTOAccionObjeto;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\FaseCombate;
use Src\Battle\Domain\Enums\ObjetivoMovimiento;
use Src\Battle\Domain\Enums\PosturaCombate;
use Src\Battle\Domain\FabricaBatallaInterface;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\ServicioEjecucionBatalla;
use Src\Battle\Domain\UsoObjetos\CatalogoEfectosItem;
use Src\Battle\Presentation\DTOAccionBatalla;
use Src\Battle\Presentation\DTOMovimientoBatalla;
use Src\Shared\Domain\Items\CatalogoItems;
use Src\Shared\Domain\Ports\InventarioJugador;

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

    /** @var array<string, string> refId => postura elegida para la ronda ('atacar'|'guardia'|'cubrir') */
    public array $posturas = [];

    /** Panel de preparación de ronda visible (no bloquea el turno: jugar lo omite). */
    public bool $preparacionPendiente = false;

    /** @var array<string, list<array{index: int, nombre: string, prioridad: int}>> */
    public array $prioridadesDisponibles = [];

    /** @var list<array<string, mixed>> Opciones del panel de objetos de combate. */
    public array $objetosCombate = [];

    /** Clave de inventario del objeto seleccionado para usar. */
    public string $objetoSeleccionado = '';

    /** Objetivo de item activo (`ObjetivosItem`->value) o cadena vacía. */
    public string $objetivosItem = '';

    private FabricaBatallaInterface $fabricaBatalla;

    private BattleSessionService $session;

    private ?ServicioEjecucionBatalla $servicioEjecucion = null;

    private GrabadorEventosBatalla $grabadorEventos;

    private SincronizadorVistaBatalla $sincronizadorVista;

    private ColaTurnosBatalla $colaTurnos;

    private EstadoAnimacionBatalla $estadoAnimacion;

    private ResolutorAccionBatalla $resolutorAccion;

    private ResolutorPosicionCombatiente $resolutorPosicion;

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
        $this->grabadorEventos ??= new GrabadorEventosBatalla();
        $this->sincronizadorVista ??= app(SincronizadorVistaBatalla::class);
        $this->resolutorAccion ??= new ResolutorAccionBatalla($this->grabadorEventos);
        $this->resolutorPosicion ??= new ResolutorPosicionCombatiente();
        $this->colaTurnos ??= new ColaTurnosBatalla($this->resolutorPosicion);
        $this->estadoAnimacion ??= new EstadoAnimacionBatalla();
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
        $vista = BatallaVistaPresenter::toView(
            team1: $this->team1,
            team2: $this->team2,
            turnQueue: $this->turnQueue,
            phase: $this->phase,
            actingRefId: $this->actingRefId,
            selectedTargetRefId: $this->selectedTargetRefId,
            animAttackerId: $this->animAttackerId,
            animDefenderId: $this->animDefenderId,
            animAttackerNombre: $this->animAttackerNombre,
            animDefenderNombre: $this->animDefenderNombre,
            animMoveNombre: $this->animMoveNombre,
            weather: $this->weather,
            log: $this->log,
            eventos: $this->eventos,
            rewards: $this->rewards,
            objetivosItem: $this->objetivosItem,
        );

        $vista['movesResumen'] = MovesPreviewPresenter::resumen($this->currentMoves);

        return view('livewire.combate', $vista)
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

        if ($battle->rondaPreparada() !== $turnos->round() && ! $this->preparacionPendiente) {
            $this->abrirPreparacion($battle);
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
        // El actor autorizado vive en el agregado (server-side), no en la
        // propiedad pública `actingRefId`.
        $battle->setActorActualId($actor->id());

        // Verificar si el actor puede actuar (sueño, hielo, parálisis, confusión)
        $antesEstado = $this->grabandoEventos ? $this->capturarEstado($battle) : SnapshotBatalla::vacia();
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
        $posicion = $this->resolutorPosicion->localizar($actor, $this->team1, $this->team2);

        if ($posicion === null) {
            $this->session->guardar($this->battleId, $battle);
            $this->processing = false;

            return;
        }

        $isPlayer = $posicion->team === 0;

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

    private function advanceRound(AgregadoBatalla $battle): void
    {
        $rondaNueva = $battle->turnManager()->round() + 1;
        $previoFin = $this->grabandoEventos ? $this->capturarEstado($battle) : SnapshotBatalla::vacia();
        $this->preparacionPendiente = false;

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
        $this->preparacionPendiente = true;
        $this->prioridades = [];
        $this->prioridadesDisponibles = [];
        $this->posturas = [];

        foreach ($battle->team1->combatientesCollection()->vivos() as $combatiente) {
            $this->prioridades[$combatiente->id()] = '';
            $this->posturas[$combatiente->id()] = $combatiente->postura()->value;

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
        if ($battle === null || ! $this->preparacionPendiente
            || $this->processing || $this->animAttackerId !== '') {
            return;
        }

        $this->aplicarPreparacion($battle, true);

        $this->preparacionPendiente = false;
        $this->log[] = "--- Ronda {$battle->turnManager()->round()}: preparación confirmada ---";

        $this->phase = FaseCombate::INICIO->value;
        $this->syncViewData($battle);
        $this->resolverPrioridades($battle);
        $this->limpiarEstadoPreparacion();
        $this->session->guardar($this->battleId, $battle);
        $this->nextActor();
    }

    public function saltarPreparacion(): void
    {
        $battle = $this->session->cargar($this->battleId);
        if ($battle === null || ! $this->preparacionPendiente
            || $this->processing || $this->animAttackerId !== '') {
            return;
        }

        $this->aplicarPreparacion($battle, false);

        $this->preparacionPendiente = false;
        $this->log[] = "--- Ronda {$battle->turnManager()->round()}: preparación omitida ---";

        $this->phase = FaseCombate::INICIO->value;
        $this->syncViewData($battle);
        $this->resolverPrioridades($battle);
        $this->limpiarEstadoPreparacion();
        $this->session->guardar($this->battleId, $battle);
        $this->nextActor();
    }

    private function aplicarPreparacion(AgregadoBatalla $battle, bool $conPrioridades): void
    {
        $equipoJugador = $battle->team1;
        $vivosJugador = $equipoJugador->combatientesCollection()->vivos()->all();

        foreach ($vivosJugador as $combatiente) {
            $postura = PosturaCombate::tryFrom($this->posturas[$combatiente->id()] ?? '')
                ?? PosturaCombate::ATACAR;
            $combatiente->setPostura($postura);

            if (! $conPrioridades) {
                $combatiente->limpiarPrioridad();

                continue;
            }

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
    }

    private function autoSaltarPreparacion(AgregadoBatalla $battle): void
    {
        if (! $this->preparacionPendiente) {
            return;
        }

        $this->aplicarPreparacion($battle, false);
        $this->preparacionPendiente = false;
        $this->log[] = "--- Ronda {$battle->turnManager()->round()}: preparación omitida al jugar ---";

        $this->syncViewData($battle);
        $this->resolverPrioridades($battle);
        $this->limpiarEstadoPreparacion();
        $this->session->guardar($this->battleId, $battle);
    }

    private function limpiarEstadoPreparacion(): void
    {
        $this->prioridades = [];
        $this->prioridadesDisponibles = [];
        $this->posturas = [];
    }

    public function cambiarPostura(string $refId, string $postura): void
    {
        $battle = $this->session->cargar($this->battleId);
        if ($battle === null || ! $this->preparacionPendiente
            || $this->processing || $this->animAttackerId !== '') {
            return;
        }

        $combatiente = $battle->team1->findCombatantById($refId);
        if ($combatiente === null || ! $combatiente->estaVivo()) {
            return;
        }

        $nuevaPostura = PosturaCombate::tryFrom($postura) ?? PosturaCombate::ATACAR;
        $combatiente->setPostura($nuevaPostura);
        $battle->team1->normalizarCubridores();

        $this->posturas[$refId] = $nuevaPostura->value;
        if ($nuevaPostura !== PosturaCombate::ATACAR) {
            // Una postura defensiva no puede usar movimientos de prioridad.
            $this->prioridades[$refId] = '';
        }

        $this->syncViewData($battle);
        $this->session->guardar($this->battleId, $battle);
    }

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

            $isAi = $battle->team2->findCombatantById($actor->id()) !== null;
            $resultadoAccion = $this->resolutorAccion->aplicarMovimiento(
                $battle,
                $actor,
                $objetivo,
                $movimiento,
                $servicio,
                $isAi ? 'RIVAL PRIORIDAD: ' : 'PRIORIDAD: ',
                $isAi ? 'RIVAL: ' : '',
                false,
                $this->eventoTick,
                count($this->eventos) + 1,
            );
            $this->log = array_merge($this->log, $resultadoAccion->log);
            $this->eventos = array_merge($this->eventos, $resultadoAccion->eventos);
        }

        $this->syncViewData($battle);
    }

    private function objetivoParaPrioridad(
        AgregadoBatalla $battle,
        Combatiente $actor,
        MovimientoBatalla $movimiento,
    ): ?Combatiente {
        $autodirigido = $movimiento->esAutodirigido()
            || $movimiento->objetivo === ObjetivoMovimiento::ALL;

        if ($autodirigido) {
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
            $this->eventos[] = $this->grabadorEventos->eventoBatallaTerminada(
                "¡{$winner} gana la batalla!",
                ! $battle->team1->todosDebilitados(),
                $this->eventoTick,
                count($this->eventos) + 1,
            );
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
        if ($movimiento->esAutodirigido()) {
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
            // `nextActor()` recarga la batalla desde sesión y puede aplicar
            // efectos de fin de ronda (p. ej. Presión). El evento `fin` debe
            // capturar esa instancia, no la hidratada al inicio del commit.
            $this->cerrarEventos();
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

        if ($pending instanceof DTOAccionObjeto) {
            $this->resolverAccionObjeto($battle, $pending);

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
        if ($movimiento->esAutodirigido()) {
            $objetivo = $actor;
        }

        // 1. Calcular y aplicar el movimiento (servicio compartido)
        $servicio = $this->servicioEjecucion ?? new ServicioEjecucionBatalla($battle->damageChain());
        $isAi = $battle->team2->findCombatantById($pending->actorId) !== null;
        $prefix = $isAi ? 'RIVAL: ' : '';
        $resultadoAccion = $this->resolutorAccion->aplicarMovimiento(
            $battle,
            $actor,
            $objetivo,
            $movimiento,
            $servicio,
            $prefix,
            $prefix,
            $this->grabandoEventos,
            $this->eventoTick,
            count($this->eventos) + 1,
        );
        $this->log = array_merge($this->log, $resultadoAccion->log);
        $this->eventos = array_merge($this->eventos, $resultadoAccion->eventos);
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
        if ($this->phase === FaseCombate::SELECCION_OBJETIVO_ITEM->value) {
            $this->confirmarObjetivoItem($teamIdx, $pokemonIdx);

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

        $target = $this->getTargetFromSelection($battle, $teamIdx, $pokemonIdx);
        if ($target === null || ! $target->estaVivo()) {
            return;
        }

        $this->selectedTargetTeam = $teamIdx;
        $this->selectedTargetIdx = $pokemonIdx;
        $this->selectedTargetRefId = $target->id();

        $this->currentMoves = MovesPreviewPresenter::toView($actor, $battle, $target);
        $this->phase = FaseCombate::SELECCION_MOVIMIENTO->value;
        $this->session->guardar($this->battleId, $battle);
    }

    public function selectMove(int $index): void
    {
        $battle = $this->session->cargar($this->battleId);
        if ($battle === null) {
            return;
        }

        if ($this->preparacionPendiente) {
            $this->autoSaltarPreparacion($battle);
        }

        $actor = $this->resolveActor($battle);
        if ($actor === null || ! $actor->estaVivo() || ! $actor->tieneAccionPendienteEstaRonda()) {
            $this->nextActor();

            return;
        }

        if ($actor->postura() !== PosturaCombate::ATACAR) {
            $this->log[] = "{$actor->nombre()} mantiene la postura de {$actor->postura()->label()}.";
            $battle->turnManager()->consumeAction($actor);
            $this->resetAnimState();
            $this->syncViewData($battle);
            $this->session->guardar($this->battleId, $battle);
            $this->nextActor();

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
        $selfTargeting = $move->esAutodirigido();
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
            $this->cancelTarget();
            $this->syncViewData($battle, $actor);
            $this->session->guardar($this->battleId, $battle);

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
        $this->limpiarEstadoObjeto();
        $this->phase = FaseCombate::SELECCION_OBJETIVO->value;
    }

    public function seleccionarObjeto(string $clave): void
    {
        if ($this->phase !== FaseCombate::SELECCION_OBJETIVO->value) {
            return;
        }

        if ($this->processing || $this->animAttackerId !== '') {
            return;
        }

        if (! CatalogoItems::existe($clave) || ! CatalogoEfectosItem::existe($clave)) {
            return;
        }

        $definicion = CatalogoItems::definicion($clave);
        if (! $definicion->usableEnCombate) {
            return;
        }

        $userId = (int) Auth::id();
        if ($userId <= 0) {
            return;
        }

        $battle = $this->session->cargar($this->battleId);
        if ($battle === null) {
            return;
        }

        // El actor se deriva del estado del agregado en servidor:
        // `actingRefId` es una propiedad pública manipulable por el cliente.
        $actor = $this->actorJugadorActual($battle);
        if ($actor === null) {
            return;
        }

        $saldo = app(InventarioJugador::class)->saldo($userId, $clave);
        if ($saldo <= 0) {
            $this->log[] = "No tienes unidades de {$definicion->nombre}.";
            $this->syncViewData($battle, $actor);
            $this->session->guardar($this->battleId, $battle);

            return;
        }

        $efecto = CatalogoEfectosItem::efecto($clave);
        $this->objetoSeleccionado = $clave;
        $this->objetivosItem = $efecto->objetivos()->value;
        $this->selectedTargetTeam = null;
        $this->selectedTargetIdx = null;
        $this->selectedTargetRefId = '';
        $this->phase = FaseCombate::SELECCION_OBJETIVO_ITEM->value;
        $this->syncViewData($battle, $actor);
        $this->session->guardar($this->battleId, $battle);
    }

    public function cancelarObjeto(): void
    {
        if ($this->phase !== FaseCombate::SELECCION_OBJETIVO_ITEM->value) {
            return;
        }

        $this->limpiarEstadoObjeto();
        $this->selectedTargetTeam = null;
        $this->selectedTargetIdx = null;
        $this->selectedTargetRefId = '';
        $this->phase = FaseCombate::SELECCION_OBJETIVO->value;
    }

    public function confirmarObjetivoItem(int $teamIdx, int $pokemonIdx): void
    {
        if ($this->phase !== FaseCombate::SELECCION_OBJETIVO_ITEM->value) {
            return;
        }

        if ($this->processing || $this->animAttackerId !== '') {
            return;
        }

        if ($this->objetoSeleccionado === '') {
            return;
        }

        $battle = $this->session->cargar($this->battleId);
        if ($battle === null) {
            return;
        }

        // `actingRefId`, `phase` y `processing` son propiedades públicas: la
        // autorización real se deriva del estado server-side del agregado.
        $actor = $this->actorJugadorActual($battle);
        if ($actor === null) {
            return;
        }

        // El objeto puede haber cambiado de estado entre la selección y la
        // confirmación (stock agotado, catálogo actualizado).
        if (! CatalogoItems::existe($this->objetoSeleccionado)
            || ! CatalogoEfectosItem::existe($this->objetoSeleccionado)) {
            $this->log[] = 'Ese objeto no se puede usar en combate.';

            return;
        }

        $item = CatalogoItems::definicion($this->objetoSeleccionado);
        if (! $item->usableEnCombate) {
            $this->log[] = "{$item->nombre} no se puede usar en combate.";

            return;
        }

        $userId = (int) Auth::id();
        $saldo = $userId > 0
            ? app(InventarioJugador::class)->saldo($userId, $this->objetoSeleccionado)
            : 0;
        if ($saldo <= 0) {
            $this->log[] = "No tienes unidades de {$item->nombre}.";
            $this->cancelarObjeto();

            return;
        }

        $objetivo = $this->getTargetFromSelection($battle, $teamIdx, $pokemonIdx);
        if ($objetivo === null) {
            return;
        }

        $accion = new AccionObjeto(
            actorId: $actor->id(),
            objetivoId: $objetivo->id(),
            clave: $this->objetoSeleccionado,
        );

        $motivo = app(UsarObjetoEnCombate::class)->validar($battle, $accion);
        if ($motivo !== null) {
            $this->log[] = $motivo;
            $this->syncViewData($battle, $actor);
            $this->session->guardar($this->battleId, $battle);

            return;
        }

        $battle->setPendingAction(new DTOAccionObjeto(
            actorId: $actor->id(),
            objetivoId: $objetivo->id(),
            clave: $this->objetoSeleccionado,
        ));

        $this->selectedTargetTeam = $teamIdx;
        $this->selectedTargetIdx = $pokemonIdx;
        $this->selectedTargetRefId = $objetivo->id();

        $this->setAnimStateObjeto($actor, $objetivo, $item->nombre);
        $this->syncViewData($battle, $actor);
        $this->session->guardar($this->battleId, $battle);
    }

    private function resolverAccionObjeto(AgregadoBatalla $battle, DTOAccionObjeto $pending): void
    {
        $actor = $battle->team1->findCombatantById($pending->actorId)
            ?? $battle->team2->findCombatantById($pending->actorId);
        $objetivo = $battle->team1->findCombatantById($pending->objetivoId)
            ?? $battle->team2->findCombatantById($pending->objetivoId);

        if ($actor === null || $objetivo === null) {
            $battle->setPendingAction(null);
            $this->resetAnimState();
            $this->session->guardar($this->battleId, $battle);
            $this->nextActor();

            return;
        }

        // La acción pendiente viaja en el agregado: solo se ejecuta si el
        // actor sigue siendo el actual y está en el equipo del jugador.
        $actorActual = $this->actorJugadorActual($battle);
        if ($actorActual === null || $actorActual->id() !== $pending->actorId) {
            $battle->setPendingAction(null);
            $this->resetAnimState();
            $this->syncViewData($battle);
            $this->session->guardar($this->battleId, $battle);
            $this->nextActor();

            return;
        }
        $resultadoAccion = $this->resolutorAccion->resolverObjeto(
            $battle,
            $actor,
            $objetivo,
            $pending->toDomain(),
            app(UsarObjetoEnCombate::class),
            (int) Auth::id(),
            $this->grabandoEventos,
            $this->eventoTick,
            count($this->eventos) + 1,
        );
        $this->log = array_merge($this->log, $resultadoAccion->log);
        $this->eventos = array_merge($this->eventos, $resultadoAccion->eventos);
        $battle->setPendingAction(null);
        $this->resetAnimState();
        $this->syncViewData($battle, $actor);
        $this->session->guardar($this->battleId, $battle);
        $this->nextActor();
    }

    private function limpiarEstadoObjeto(): void
    {
        $this->objetoSeleccionado = '';
        $this->objetivosItem = '';
    }

    public function swapTurno(): void
    {
        if (! in_array($this->phase, [
            FaseCombate::SELECCION_OBJETIVO->value,
            FaseCombate::SELECCION_MOVIMIENTO->value,
            FaseCombate::SELECCION_OBJETIVO_ITEM->value,
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

    public function retrasarTurno(): void
    {
        if (! in_array($this->phase, [
            FaseCombate::SELECCION_OBJETIVO->value,
            FaseCombate::SELECCION_MOVIMIENTO->value,
            FaseCombate::SELECCION_OBJETIVO_ITEM->value,
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

        if ($this->preparacionPendiente) {
            $this->autoSaltarPreparacion($battle);
        }

        $actor = $this->resolveActor($battle);
        if ($actor === null || ! $actor->estaVivo()) {
            $this->nextActor();

            return;
        }

        if (! $actor->retrasarTurno()) {
            return;
        }

        $this->log[] = "{$actor->nombre()} retrasa su turno.";
        $this->resetAnimState();
        $this->selectedMoveIdx = null;
        $this->session->guardar($this->battleId, $battle);
        $this->nextActor();
    }

    public function pasarTurno(): void
    {
        if (! in_array($this->phase, [
            FaseCombate::SELECCION_OBJETIVO->value,
            FaseCombate::SELECCION_MOVIMIENTO->value,
            FaseCombate::SELECCION_OBJETIVO_ITEM->value,
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

        if ($this->preparacionPendiente) {
            $this->autoSaltarPreparacion($battle);
        }

        $actor = $this->resolveActor($battle);
        if ($actor === null || ! $actor->estaVivo()) {
            $this->nextActor();

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

    private function resolveActor(AgregadoBatalla $battle): ?Combatiente
    {
        return $battle->team1->findCombatantById($this->actingRefId)
            ?? $battle->team2->findCombatantById($this->actingRefId);
    }

    /**
     * Resuelve el actor del jugador autorizado para acciones de objeto.
     * Lee del agregado (server-side); `actingRefId` no es fuente de verdad.
     * El fallback a `getNextActor()` solo cubre sesiones antiguas sin
     * `actorActualId`, incluido el actor forzado por `swapTurno()`.
     */
    private function actorJugadorActual(AgregadoBatalla $battle): ?Combatiente
    {
        $actorId = $battle->actorActualId();

        if ($actorId === null) {
            $actorId = $battle->turnManager()->getNextActor()?->id();
        }

        if ($actorId === null) {
            return null;
        }

        $actor = $battle->team1->findCombatantById($actorId);

        if ($actor === null || ! $actor->estaVivo() || ! $actor->tieneAccionPendienteEstaRonda()) {
            return null;
        }

        return $actor;
    }

    private function getTargetFromSelection(AgregadoBatalla $battle, ?int $teamIdx, ?int $pokemonIdx): ?Combatiente
    {
        if ($teamIdx === null || $pokemonIdx === null) {
            return null;
        }

        $team = $teamIdx === 0 ? $battle->team1 : $battle->team2;

        return $team->combatants()->en($pokemonIdx);
    }

    // ─── Eventos de animación ────────────────────────────────

    private function capturarEstado(AgregadoBatalla $battle): SnapshotBatalla
    {
        return $this->grabadorEventos->capturar($battle);
    }

    private function iniciarEventos(): void
    {
        $this->eventos = [];
        $this->eventoTick++;
        $this->grabandoEventos = true;
    }

    private function cerrarEventos(): void
    {
        if ($this->grabandoEventos) {
            $battle = $this->session->cargar($this->battleId);

            if ($battle !== null) {
                $this->eventos[] = $this->grabadorEventos->eventoFin(
                    $this->capturarEstado($battle),
                    $this->eventoTick,
                    count($this->eventos) + 1,
                );
            }
        }

        $this->grabandoEventos = false;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function registrarEvento(string $tipo, SnapshotBatalla $antes, SnapshotBatalla $despues, array $extra = []): void
    {
        if (! $this->grabandoEventos) {
            return;
        }

        $evento = $this->grabadorEventos->registrar(
            $tipo,
            $antes,
            $despues,
            $this->eventoTick,
            count($this->eventos) + 1,
            $extra,
        );
        if ($evento !== null) {
            $this->eventos[] = $evento;
        }
    }

    /**
     * @return list<array{team: int, index: int}>
     */
    private function buildTurnQueue(AgregadoBatalla $battle): array
    {
        return $this->colaTurnos->construir(
            $battle,
            $this->actingRefId,
            $this->team1,
            $this->team2,
        );
    }

    // ─── Animación ───────────────────────────────────────────

    private function setAnimState(Combatiente $actor, Combatiente $target, MovimientoBatalla $move): void
    {
        $this->animTick++;
        $animacion = $this->estadoAnimacion->paraMovimiento($actor, $target, $move);

        $this->animAttackerId = $animacion->attackerId;
        $this->animAttackerNombre = $animacion->attackerNombre;
        $this->animMoveNombre = $animacion->moveNombre;
        $this->animDefenderId = $animacion->defenderId;
        $this->animDefenderNombre = $animacion->defenderNombre;
    }

    private function setAnimStateObjeto(Combatiente $actor, Combatiente $target, string $itemNombre): void
    {
        $this->animTick++;
        $animacion = $this->estadoAnimacion->paraObjeto($actor, $target, $itemNombre);

        $this->animAttackerId = $animacion->attackerId;
        $this->animAttackerNombre = $animacion->attackerNombre;
        $this->animMoveNombre = $animacion->moveNombre;
        $this->animDefenderId = $animacion->defenderId;
        $this->animDefenderNombre = $animacion->defenderNombre;
    }

    private function resetAnimState(): void
    {
        $animacion = $this->estadoAnimacion->reiniciada();
        $this->animAttackerId = $animacion->attackerId;
        $this->animDefenderId = $animacion->defenderId;
        $this->animAttackerNombre = $animacion->attackerNombre;
        $this->animDefenderNombre = $animacion->defenderNombre;
        $this->animMoveNombre = $animacion->moveNombre;
        $this->selectedTargetTeam = null;
        $this->selectedTargetIdx = null;
        $this->selectedTargetRefId = '';
        $this->limpiarEstadoObjeto();
    }

    // ─── Sincronizar vista ───────────────────────────────────

    private function syncViewData(AgregadoBatalla $battle, ?Combatiente $actor = null): void
    {
        $actorParaVista = $actor;
        if ($actorParaVista !== null && ! $battle->team1->findCombatant($actorParaVista)) {
            $actorParaVista = null;
        }

        $vista = $this->sincronizadorVista->proyectar(
            $battle,
            $actorParaVista,
            $actor ?? $this->resolveActor($battle),
            (int) Auth::id(),
        );

        $this->weather = $vista->weather;
        $this->log = array_merge($this->log, $vista->logNuevo);
        $this->team1 = $vista->team1;
        $this->team2 = $vista->team2;
        $this->objetosCombate = $vista->objetosCombate;
    }
}
