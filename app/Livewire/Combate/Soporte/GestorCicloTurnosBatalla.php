<?php

declare(strict_types=1);

namespace App\Livewire\Combate\Soporte;

use App\Livewire\Combate;
use App\Livewire\Combate\Soporte\DTO\SnapshotBatalla;
use App\Support\BattleSessionService;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\FaseCombate;
use Src\Battle\Domain\Enums\PosturaCombate;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Presentation\DTOMovimientoBatalla;

/**
 * Avanza el ciclo de turnos y rondas. Sin estado: conserva las guardas y el
 * orden exacto de eventos, posición, vista y llamadas a `nextActor()`.
 */
final class GestorCicloTurnosBatalla
{
    public function __construct(
        private readonly GestorEventosBatalla $gestorEventos,
        private readonly GestorVistaBatalla $gestorVista,
        private readonly GestorAccionBatalla $gestorAccion,
        private readonly GestorPreparacionBatalla $gestorPreparacion,
        private readonly ResolutorPosicionCombatiente $resolutorPosicion,
        private readonly ColaTurnosBatalla $colaTurnos,
        private readonly BattleSessionService $session,
    ) {
    }

    public function avanzar(
        Combate $componente,
        ContextoEjecucionBatalla $contexto,
        bool $grabando,
    ): void {
        $battle = $this->session->cargar($componente->battleId);
        if ($battle === null) {
            return;
        }

        $turnos = $battle->turnManager();

        if (! $turnos->bothTeamsAlive()) {
            $this->gestorAccion->terminar($componente, $battle, $grabando);

            return;
        }

        // Al inicio (ronda 0) o si todos actuaron, avanzar ronda
        if ($turnos->round() === 0 || ! $turnos->hayAlgunoConAccionPendiente()) {
            if ($this->avanzarRondaOTerminar($componente, $battle, $grabando)) {
                return;
            }
        }

        if ($battle->rondaPreparada() !== $turnos->round() && ! $componente->preparacionPendiente) {
            $this->gestorPreparacion->abrir($componente, $battle);
        }

        $actor = $turnos->getNextActor();

        // Sin actor pendiente (caso defensivo): forzar nueva ronda
        if ($actor === null) {
            if ($this->avanzarRondaOTerminar($componente, $battle, $grabando)) {
                return;
            }

            $actor = $turnos->getNextActor();
            if ($actor === null) {
                $this->gestorAccion->terminar($componente, $battle, $grabando);

                return;
            }
        }

        if ($contexto->forzarSiguienteActor() !== null) {
            $forzado = $battle->team1->findCombatantById($contexto->forzarSiguienteActor());
            $contexto->fijarForzarSiguienteActor(null);

            if ($forzado !== null && $forzado->estaVivo() && $forzado->tieneAccionPendienteEstaRonda()) {
                $actor = $forzado;
            }
        }

        $componente->actingRefId = $actor->id();
        // El actor autorizado vive en el agregado (server-side), no en la
        // propiedad pública `actingRefId`.
        $battle->setActorActualId($actor->id());

        // Verificar si el actor puede actuar (sueño, hielo, parálisis, confusión)
        $antesEstado = $grabando ? $this->gestorEventos->capturar($battle) : SnapshotBatalla::vacia();
        $statusCheck = $actor->puedeActuar();
        if (! $statusCheck->esPermitida()) {
            $actor->effects()->dispararTurnoPerdido($actor, $battle);
        }
        if ($grabando) {
            $motivo = $statusCheck->autoDanio() > 0
                ? "{$actor->nombre()} se golpeó a sí mismo ({$statusCheck->autoDanio()} daño)"
                : "{$actor->nombre()} {$statusCheck->motivo()}";

            $this->gestorEventos->registrar(
                $componente,
                $grabando,
                $statusCheck->esPermitida() ? 'status' : 'status_block',
                $antesEstado,
                $this->gestorEventos->capturar($battle),
                [
                    'actorId' => $actor->id(),
                    'texto' => $motivo,
                    'forzar' => ! $statusCheck->esPermitida(),
                ],
            );
        }
        if (! $statusCheck->esPermitida()) {
            $componente->log[] = "{$actor->nombre()} {$statusCheck->motivo()}!";
            if ($statusCheck->autoDanio() > 0) {
                $componente->log[] = "¡{$actor->nombre()} se golpeó a sí mismo! ({$statusCheck->autoDanio()} daño)";
            }
            $turnos->consumeAction($actor);
            $this->gestorVista->sincronizar($componente, $battle);
            $this->session->guardar($componente->battleId, $battle);
            $componente->nextActor();

            return;
        }
        if ($statusCheck->motivo() === 'despertó' || $statusCheck->motivo() === 'se descongeló') {
            $componente->log[] = "¡{$actor->nombre()} {$statusCheck->motivo()}!";
        }

        if ($actor->postura() !== PosturaCombate::ATACAR) {
            $componente->log[] = "{$actor->nombre()} mantiene la postura de {$actor->postura()->label()}.";
            $turnos->consumeAction($actor);
            $this->gestorVista->sincronizar($componente, $battle);
            $this->session->guardar($componente->battleId, $battle);
            $componente->nextActor();

            return;
        }

        $this->gestorVista->sincronizar($componente, $battle, $actor);
        $posicion = $this->resolutorPosicion->localizar($actor, $componente->team1, $componente->team2);

        if ($posicion === null) {
            $this->session->guardar($componente->battleId, $battle);
            $componente->processing = false;

            return;
        }

        $isPlayer = $posicion->team === 0;

        if ($isPlayer) {
            if (! $componente->preparacionPendiente
                && $this->sinAccionLegal($actor, $componente->objetosCombate)) {
                $componente->log[] = "{$actor->nombre()} no tiene ninguna acción disponible y pierde el turno.";
                $turnos->consumeAction($actor);
                $componente->selectedMoveIdx = null;
                $componente->processing = false;
                $this->session->guardar($componente->battleId, $battle);
                $componente->nextActor();

                return;
            }

            $componente->currentMoves = array_map(
                fn (MovimientoBatalla $m) => DTOMovimientoBatalla::desdeDominio($m)->toLivewire(),
                $actor->pokemon()->moves()->all()
            );
            $componente->phase = FaseCombate::SELECCION_OBJETIVO->value;
            $componente->processing = false;
        } else {
            $componente->processing = true;
            if (! $this->gestorAccion->prepararAnimacionIa($componente, $battle, $actor)) {
                // El turno se cerró sin acción y ya se avanzó al siguiente
                // actor: no reconstruir la cola con el estado anterior.
                return;
            }

            $componente->turnQueue = $this->colaTurnos->construir(
                $battle,
                $componente->actingRefId,
                $componente->team1,
                $componente->team2,
            );

            return; // la animación llama commitAction() via Alpine
        }

        $componente->turnQueue = $this->colaTurnos->construir(
            $battle,
            $componente->actingRefId,
            $componente->team1,
            $componente->team2,
        );
        $this->session->guardar($componente->battleId, $battle);
    }

    /**
     * ¿El actor del jugador carece de toda acción legal en esta ronda?
     * La postura defensiva ya se consumió antes de llegar aquí.
     *
     * @param  list<array<string, mixed>>  $objetosCombate
     */
    private function sinAccionLegal(Combatiente $actor, array $objetosCombate): bool
    {
        foreach ($actor->pokemon()->moves() as $movimiento) {
            if ($movimiento instanceof MovimientoBatalla && $actor->movimientoDisponible($movimiento)) {
                return false;
            }
        }

        foreach ($objetosCombate as $objeto) {
            if (($objeto['aplicable'] ?? false) === true) {
                return false;
            }
        }

        return true;
    }

    private function avanzarRonda(Combate $componente, AgregadoBatalla $battle, bool $grabando): void
    {
        $rondaNueva = $battle->turnManager()->round() + 1;
        $previoFin = $grabando ? $this->gestorEventos->capturar($battle) : SnapshotBatalla::vacia();
        $componente->preparacionPendiente = false;

        if ($battle->turnManager()->round() > 0) {
            $battle->triggerRoundEndEffects();
        }

        $battle->turnManager()->startNewRound();
        $battle->triggerRoundStartEffects();

        // Un único evento para cierre + inicio de ronda. Así Presión y
        // cualquier regeneración de inicio se muestran como un cambio neto y
        // no como “pierde escudo → lo recupera” en dos pasos.
        $this->gestorEventos->registrar(
            $componente,
            $grabando,
            'round',
            $previoFin,
            $this->gestorEventos->capturar($battle),
            [
                'ronda' => $rondaNueva,
                'forzar' => true,
            ],
        );

        $componente->round = $battle->turnManager()->round();
        $componente->log[] = "--- Ronda {$componente->round} ---";
    }

    private function avanzarRondaOTerminar(
        Combate $componente,
        AgregadoBatalla $battle,
        bool $grabando,
    ): bool {
        $this->avanzarRonda($componente, $battle, $grabando);

        if ($battle->turnManager()->bothTeamsAlive()) {
            return false;
        }

        $this->gestorAccion->terminar($componente, $battle, $grabando);

        return true;
    }
}
