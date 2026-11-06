<?php

declare(strict_types=1);

namespace App\Livewire\Combate\Soporte;

use App\Livewire\Combate;
use App\Livewire\Presenters\PresentadorResultadoBatalla;
use App\Support\BattleSessionService;
use Illuminate\Contracts\Auth\Guard;
use Src\Battle\App\UsarObjetoEnCombate;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\DataTransferObjects\DTOAccionObjeto;
use Src\Battle\Domain\Enums\FaseCombate;
use Src\Battle\Domain\ServicioEjecucionBatalla;
use Src\Battle\Presentation\DTOAccionBatalla;
use Src\Battle\Presentation\DTOMovimientoBatalla;

/**
 * Ejecuta y cierra las acciones de batalla. Sin estado: la sesión, la vista,
 * la animación y los eventos se resuelven con colaboradores especializados.
 */
final class GestorAccionBatalla
{
    public function __construct(
        private readonly GestorEventosBatalla $gestorEventos,
        private readonly GestorVistaBatalla $gestorVista,
        private readonly GestorAnimacionBatalla $gestorAnimacion,
        private readonly ResolutorAccionBatalla $resolutorAccion,
        private readonly ResolutorActorBatalla $resolutorActor,
        private readonly BattleSessionService $session,
        private readonly UsarObjetoEnCombate $usarObjeto,
        private readonly Guard $guard,
    ) {
    }

    public function commit(Combate $componente, ContextoEjecucionBatalla $contexto, bool &$grabando): void
    {
        $battle = $this->session->cargar($componente->battleId);
        if ($battle === null) {
            return;
        }

        $this->gestorEventos->iniciar($componente, $grabando);

        try {
            $this->resolver($componente, $battle, $contexto, $grabando);
        } finally {
            // `nextActor()` recarga la batalla desde sesión y puede aplicar
            // efectos de fin de ronda (p. ej. Presión). El evento `fin` debe
            // capturar esa instancia, no la hidratada al inicio del commit.
            $this->gestorEventos->cerrar($componente, $grabando);
        }
    }

    public function terminar(Combate $componente, AgregadoBatalla $battle, bool $grabando): void
    {
        $componente->phase = FaseCombate::BATALLA_TERMINADA->value;
        $winner = ! $battle->team1->todosDebilitados() ? $battle->team1->name : $battle->team2->name;
        $componente->log[] = "¡{$winner} gana la batalla!";
        $this->gestorAnimacion->reiniciar($componente);
        $this->gestorVista->sincronizar($componente, $battle);
        $this->session->guardar($componente->battleId, $battle);
        $componente->processing = false;

        $resultado = PresentadorResultadoBatalla::procesar($battle, $componente->battleId, $this->session);
        $componente->log = array_merge($componente->log, $resultado['log']);
        $componente->rewards = $resultado['rewards'];
        $componente->habitatId = $resultado['habitatId'];

        $this->gestorEventos->batallaTerminada(
            $componente,
            $grabando,
            "¡{$winner} gana la batalla!",
            ! $battle->team1->todosDebilitados(),
        );
    }

    public function prepararAnimacionIa(Combate $componente, AgregadoBatalla $battle, Combatiente $actor): void
    {
        $objetivo = $battle->elegirObjetivoPara($actor);
        if ($objetivo === null) {
            return;
        }

        $movimiento = $battle->elegirMejorMovimiento($actor, $objetivo);
        if ($movimiento === null || ! $actor->movimientoDisponible($movimiento)) {
            $componente->log[] = "{$actor->nombre()} no tiene movimientos disponibles.";
            $battle->turnManager()->consumeAction($actor);
            $this->gestorVista->sincronizar($componente, $battle, $actor);
            $this->session->guardar($componente->battleId, $battle);
            $componente->nextActor();

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

        $this->gestorAnimacion->movimiento($componente, $actor, $targetForAnim, $movimiento);
        $this->gestorVista->sincronizar($componente, $battle, $actor);
        $this->session->guardar($componente->battleId, $battle);
    }

    private function resolver(
        Combate $componente,
        AgregadoBatalla $battle,
        ContextoEjecucionBatalla $contexto,
        bool $grabando,
    ): void {
        $pending = $battle->pendingAction();
        if ($pending === null) {
            $this->gestorAnimacion->reiniciar($componente);
            $componente->processing = false;

            return;
        }

        if ($pending instanceof DTOAccionObjeto) {
            $this->resolverObjeto($componente, $battle, $pending, $grabando);

            return;
        }

        $actor = $battle->team1->findCombatantById($pending->actorId)
            ?? $battle->team2->findCombatantById($pending->actorId);
        $objetivo = $battle->team1->findCombatantById($pending->defenderId)
            ?? $battle->team2->findCombatantById($pending->defenderId);

        if ($actor === null || $objetivo === null) {
            $actor?->romperCombo();
            $battle->setPendingAction(null);
            $this->session->guardar($componente->battleId, $battle);
            $this->gestorAnimacion->reiniciar($componente);
            $componente->nextActor();

            return;
        }

        $movimiento = $pending->move->toDomain();

        if (! $actor->movimientoDisponible($movimiento)) {
            $actor->romperCombo();
            $componente->log[] = "{$actor->nombre()} ya no puede usar {$movimiento->nombre}.";
            $battle->setPendingAction(null);
            $battle->turnManager()->consumeAction($actor);
            $this->gestorAnimacion->reiniciar($componente);
            $this->gestorVista->sincronizar($componente, $battle, $actor);
            $this->session->guardar($componente->battleId, $battle);
            $componente->nextActor();

            return;
        }

        // Movimientos autodirigidos (ej: Danza Espada) siempre apuntan al actor
        if ($movimiento->esAutodirigido()) {
            $objetivo = $actor;
        }

        // 1. Calcular y aplicar el movimiento (servicio compartido)
        $servicio = $contexto->servicio() ?? new ServicioEjecucionBatalla($battle->damageChain());
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
            $grabando,
            $componente->eventoTick,
            count($componente->eventos) + 1,
        );
        $componente->log = array_merge($componente->log, $resultadoAccion->log);
        $componente->eventos = array_merge($componente->eventos, $resultadoAccion->eventos);
        // 6. Limpiar estado pendiente y seguir
        $battle->setPendingAction(null);
        $this->gestorAnimacion->reiniciar($componente);
        $this->gestorVista->sincronizar($componente, $battle, $actor);
        $this->session->guardar($componente->battleId, $battle);
        $componente->nextActor();
    }

    private function resolverObjeto(
        Combate $componente,
        AgregadoBatalla $battle,
        DTOAccionObjeto $pending,
        bool $grabando,
    ): void {
        $actor = $battle->team1->findCombatantById($pending->actorId)
            ?? $battle->team2->findCombatantById($pending->actorId);
        $objetivo = $battle->team1->findCombatantById($pending->objetivoId)
            ?? $battle->team2->findCombatantById($pending->objetivoId);

        if ($actor === null || $objetivo === null) {
            $battle->setPendingAction(null);
            $this->gestorAnimacion->reiniciar($componente);
            $this->session->guardar($componente->battleId, $battle);
            $componente->nextActor();

            return;
        }

        // La acción pendiente viaja en el agregado: solo se ejecuta si el
        // actor sigue siendo el actual y está en el equipo del jugador.
        $actorActual = $this->resolutorActor->jugadorAutorizado($battle);
        if ($actorActual === null || $actorActual->id() !== $pending->actorId) {
            $battle->setPendingAction(null);
            $this->gestorAnimacion->reiniciar($componente);
            $this->gestorVista->sincronizar($componente, $battle);
            $this->session->guardar($componente->battleId, $battle);
            $componente->nextActor();

            return;
        }
        $resultadoAccion = $this->resolutorAccion->resolverObjeto(
            $battle,
            $actor,
            $objetivo,
            $pending->toDomain(),
            $this->usarObjeto,
            (int) $this->guard->id(),
            $grabando,
            $componente->eventoTick,
            count($componente->eventos) + 1,
        );
        $componente->log = array_merge($componente->log, $resultadoAccion->log);
        $componente->eventos = array_merge($componente->eventos, $resultadoAccion->eventos);
        $battle->setPendingAction(null);
        $this->gestorAnimacion->reiniciar($componente);
        $this->gestorVista->sincronizar($componente, $battle, $actor);
        $this->session->guardar($componente->battleId, $battle);
        $componente->nextActor();
    }
}
