<?php

declare(strict_types=1);

namespace App\Livewire\Combate\Soporte;

use App\Livewire\Combate;
use App\Livewire\Presenters\MovesPreviewPresenter;
use App\Support\BattleSessionService;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\FaseCombate;
use Src\Battle\Domain\Enums\PosturaCombate;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Presentation\DTOAccionBatalla;
use Src\Battle\Presentation\DTOMovimientoBatalla;

/**
 * Interacciones del jugador sobre el turno: objetivo, movimiento, cambio de
 * turno y espera. Sin estado: cada acción recarga el agregado de sesión.
 */
final class GestorSeleccionBatalla
{
    public function __construct(
        private readonly BattleSessionService $session,
        private readonly ResolutorActorBatalla $resolutorActor,
        private readonly GestorObjetosBatalla $gestorObjetos,
        private readonly GestorPreparacionBatalla $gestorPreparacion,
        private readonly GestorVistaBatalla $gestorVista,
        private readonly GestorAnimacionBatalla $gestorAnimacion,
    ) {
    }

    public function previewTarget(Combate $componente, int $teamIdx, int $pokemonIdx): void
    {
        if ($componente->phase === FaseCombate::SELECCION_OBJETIVO_ITEM->value) {
            $this->gestorObjetos->confirmarObjetivoItem($componente, $teamIdx, $pokemonIdx);

            return;
        }

        $battle = $this->session->cargar($componente->battleId);
        if ($battle === null) {
            return;
        }

        $actor = $this->resolutorActor->porRefId($componente->actingRefId, $battle);
        if ($actor === null) {
            return;
        }

        $target = $this->resolutorActor->objetivoPorSeleccion($battle, $teamIdx, $pokemonIdx);
        if ($target === null || ! $target->estaVivo()) {
            return;
        }

        $componente->selectedTargetTeam = $teamIdx;
        $componente->selectedTargetIdx = $pokemonIdx;
        $componente->selectedTargetRefId = $target->id();

        $componente->currentMoves = MovesPreviewPresenter::toView($actor, $battle, $target);
        $componente->phase = FaseCombate::SELECCION_MOVIMIENTO->value;
        $this->session->guardar($componente->battleId, $battle);
    }

    public function selectMove(
        Combate $componente,
        ContextoEjecucionBatalla $contexto,
        int $index,
    ): void {
        $battle = $this->session->cargar($componente->battleId);
        if ($battle === null) {
            return;
        }

        if ($componente->preparacionPendiente) {
            $this->gestorPreparacion->autoSaltar($componente, $battle, $contexto);
        }

        $actor = $this->resolutorActor->porRefId($componente->actingRefId, $battle);
        if ($actor === null || ! $actor->estaVivo() || ! $actor->tieneAccionPendienteEstaRonda()) {
            $componente->nextActor();

            return;
        }

        if ($actor->postura() !== PosturaCombate::ATACAR) {
            $componente->log[] = "{$actor->nombre()} mantiene la postura de {$actor->postura()->label()}.";
            $battle->turnManager()->consumeAction($actor);
            $this->gestorAnimacion->reiniciar($componente);
            $this->gestorVista->sincronizar($componente, $battle);
            $this->session->guardar($componente->battleId, $battle);
            $componente->nextActor();

            return;
        }

        $move = $actor->pokemon()->moves()->get($index);
        if (! $move instanceof MovimientoBatalla) {
            return;
        }

        if (! $actor->movimientoDisponible($move)) {
            $componente->log[] = match ($actor->motivoMovimientoNoDisponible($move)) {
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
            : $this->resolutorActor->objetivoPorSeleccion(
                $battle,
                $componente->selectedTargetTeam,
                $componente->selectedTargetIdx,
            );

        $desafio = $actor->condicion(ClaveCondicion::DESAFIO);
        if (! $selfTargeting && $desafio !== null && $desafio->objetivoForzadoId !== '') {
            $defender = $battle->team1->findCombatantById($desafio->objetivoForzadoId)
                ?? $battle->team2->findCombatantById($desafio->objetivoForzadoId)
                ?? $defender;
        }

        if ($defender === null || ! $defender->estaVivo()) {
            $this->cancelTarget($componente);
            $this->gestorVista->sincronizar($componente, $battle, $actor);
            $this->session->guardar($componente->battleId, $battle);

            return;
        }

        $battle->setPendingAction(new DTOAccionBatalla(
            type: 'attack',
            actorId: $actor->id(),
            defenderId: $defender->id(),
            attackerNombre: $actor->nombre(),
            move: DTOMovimientoBatalla::desdeDominio($move),
        ));

        $componente->selectedMoveIdx = null;
        $this->gestorAnimacion->movimiento($componente, $actor, $defender, $move);
        $this->gestorVista->sincronizar($componente, $battle, $actor);
        $this->session->guardar($componente->battleId, $battle);
    }

    public function cancelTarget(Combate $componente): void
    {
        $componente->selectedTargetTeam = null;
        $componente->selectedTargetIdx = null;
        $componente->selectedTargetRefId = '';
        $this->gestorObjetos->limpiar($componente);
        $componente->phase = FaseCombate::SELECCION_OBJETIVO->value;
    }

    public function swapTurno(Combate $componente, ContextoEjecucionBatalla $contexto): void
    {
        if (! in_array($componente->phase, [
            FaseCombate::SELECCION_OBJETIVO->value,
            FaseCombate::SELECCION_MOVIMIENTO->value,
            FaseCombate::SELECCION_OBJETIVO_ITEM->value,
        ], true)) {
            return;
        }

        if ($componente->processing || $componente->animAttackerId !== '') {
            return;
        }

        $siguienteTurno = $componente->turnQueue[1] ?? null;
        if (($componente->turnQueue[0]['team'] ?? null) !== 0 || ($siguienteTurno['team'] ?? null) !== 0) {
            return;
        }

        $siguiente = $componente->team1[$siguienteTurno['index']] ?? null;
        if ($siguiente === null || ! $siguiente['alive'] || $siguiente['refId'] === $componente->actingRefId) {
            return;
        }

        $this->gestorAnimacion->reiniciar($componente);
        $componente->selectedMoveIdx = null;
        $contexto->fijarForzarSiguienteActor($siguiente['refId']);
        $componente->nextActor();
    }

    public function retrasarTurno(Combate $componente, ContextoEjecucionBatalla $contexto): void
    {
        if (! in_array($componente->phase, [
            FaseCombate::SELECCION_OBJETIVO->value,
            FaseCombate::SELECCION_MOVIMIENTO->value,
            FaseCombate::SELECCION_OBJETIVO_ITEM->value,
        ], true)) {
            return;
        }

        if ($componente->processing || $componente->animAttackerId !== '') {
            return;
        }

        $battle = $this->session->cargar($componente->battleId);
        if ($battle === null) {
            return;
        }

        if ($componente->preparacionPendiente) {
            $this->gestorPreparacion->autoSaltar($componente, $battle, $contexto);
        }

        $actor = $this->resolutorActor->porRefId($componente->actingRefId, $battle);
        if ($actor === null || ! $actor->estaVivo()) {
            $componente->nextActor();

            return;
        }

        if (! $actor->retrasarTurno()) {
            return;
        }

        $componente->log[] = "{$actor->nombre()} retrasa su turno.";
        $this->gestorAnimacion->reiniciar($componente);
        $componente->selectedMoveIdx = null;
        $this->session->guardar($componente->battleId, $battle);
        $componente->nextActor();
    }

    public function pasarTurno(Combate $componente, ContextoEjecucionBatalla $contexto): void
    {
        if (! in_array($componente->phase, [
            FaseCombate::SELECCION_OBJETIVO->value,
            FaseCombate::SELECCION_MOVIMIENTO->value,
            FaseCombate::SELECCION_OBJETIVO_ITEM->value,
        ], true)) {
            return;
        }

        if ($componente->processing || $componente->animAttackerId !== '') {
            return;
        }

        $battle = $this->session->cargar($componente->battleId);
        if ($battle === null) {
            return;
        }

        if ($componente->preparacionPendiente) {
            $this->gestorPreparacion->autoSaltar($componente, $battle, $contexto);
        }

        $actor = $this->resolutorActor->porRefId($componente->actingRefId, $battle);
        if ($actor === null || ! $actor->estaVivo()) {
            $componente->nextActor();

            return;
        }

        $componente->log[] = "{$actor->nombre()} espera.";
        $battle->turnManager()->consumeAction($actor);
        $this->gestorAnimacion->reiniciar($componente);
        $componente->selectedMoveIdx = null;
        $this->gestorVista->sincronizar($componente, $battle, $actor);
        $this->session->guardar($componente->battleId, $battle);
        $componente->nextActor();
    }
}
