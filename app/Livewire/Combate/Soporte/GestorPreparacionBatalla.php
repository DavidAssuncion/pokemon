<?php

declare(strict_types=1);

namespace App\Livewire\Combate\Soporte;

use App\Livewire\Combate;
use App\Support\BattleSessionService;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\FaseCombate;
use Src\Battle\Domain\Enums\PosturaCombate;
use Src\Battle\Domain\MovimientoBatalla;

/**
 * Gestiona el panel de preparación de ronda: posturas, prioridades y el
 * salto automático al jugar. Sin estado: los datos viven en el componente.
 */
final class GestorPreparacionBatalla
{
    public function __construct(
        private readonly BattleSessionService $session,
        private readonly GestorVistaBatalla $gestorVista,
        private readonly GestorAnimacionBatalla $gestorAnimacion,
        private readonly GestorPrioridadesBatalla $gestorPrioridades,
        private readonly ColaTurnosBatalla $colaTurnos,
    ) {
    }

    public function abrir(Combate $componente, AgregadoBatalla $battle): void
    {
        $componente->preparacionPendiente = true;
        $componente->prioridades = [];
        $componente->prioridadesDisponibles = [];
        $componente->posturas = [];

        foreach ($battle->team1->combatientesCollection()->vivos() as $combatiente) {
            $componente->prioridades[$combatiente->id()] = '';
            $componente->posturas[$combatiente->id()] = $combatiente->postura()->value;

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
                $componente->prioridadesDisponibles[$combatiente->id()] = $disponibles;
            }
        }

        $this->gestorVista->sincronizar($componente, $battle);
        $componente->turnQueue = $this->colaTurnos->construir(
            $battle,
            $componente->actingRefId,
            $componente->team1,
            $componente->team2,
        );
        $this->gestorAnimacion->reiniciar($componente);
        $this->session->guardar($componente->battleId, $battle);
        $componente->processing = false;
    }

    public function confirmar(Combate $componente, ContextoEjecucionBatalla $contexto): void
    {
        $battle = $this->session->cargar($componente->battleId);
        if ($battle === null || ! $componente->preparacionPendiente
            || $componente->processing || $componente->animAttackerId !== '') {
            return;
        }

        $this->aplicar($componente, $battle, true);

        $componente->preparacionPendiente = false;
        $componente->log[] = "--- Ronda {$battle->turnManager()->round()}: preparación confirmada ---";

        $componente->phase = FaseCombate::INICIO->value;
        $this->gestorVista->sincronizar($componente, $battle);
        $this->gestorPrioridades->resolver($componente, $battle, $contexto);
        $this->limpiar($componente);
        $this->session->guardar($componente->battleId, $battle);
        $componente->nextActor();
    }

    public function saltar(Combate $componente, ContextoEjecucionBatalla $contexto): void
    {
        $battle = $this->session->cargar($componente->battleId);
        if ($battle === null || ! $componente->preparacionPendiente
            || $componente->processing || $componente->animAttackerId !== '') {
            return;
        }

        $this->aplicar($componente, $battle, false);

        $componente->preparacionPendiente = false;
        $componente->log[] = "--- Ronda {$battle->turnManager()->round()}: preparación omitida ---";

        $componente->phase = FaseCombate::INICIO->value;
        $this->gestorVista->sincronizar($componente, $battle);
        $this->gestorPrioridades->resolver($componente, $battle, $contexto);
        $this->limpiar($componente);
        $this->session->guardar($componente->battleId, $battle);
        $componente->nextActor();
    }

    public function autoSaltar(
        Combate $componente,
        AgregadoBatalla $battle,
        ContextoEjecucionBatalla $contexto,
    ): void {
        if (! $componente->preparacionPendiente) {
            return;
        }

        $this->aplicar($componente, $battle, false);
        $componente->preparacionPendiente = false;
        $componente->log[] = "--- Ronda {$battle->turnManager()->round()}: preparación omitida al jugar ---";

        $this->gestorVista->sincronizar($componente, $battle);
        $this->gestorPrioridades->resolver($componente, $battle, $contexto);
        $this->limpiar($componente);
        $this->session->guardar($componente->battleId, $battle);
    }

    public function cambiarPostura(Combate $componente, string $refId, string $postura): void
    {
        $battle = $this->session->cargar($componente->battleId);
        if ($battle === null || ! $componente->preparacionPendiente
            || $componente->processing || $componente->animAttackerId !== '') {
            return;
        }

        $combatiente = $battle->team1->findCombatantById($refId);
        if ($combatiente === null || ! $combatiente->estaVivo()) {
            return;
        }

        $nuevaPostura = PosturaCombate::tryFrom($postura) ?? PosturaCombate::ATACAR;
        $combatiente->setPostura($nuevaPostura);
        $battle->team1->normalizarCubridores();

        $componente->posturas[$refId] = $nuevaPostura->value;
        if ($nuevaPostura !== PosturaCombate::ATACAR) {
            // Una postura defensiva no puede usar movimientos de prioridad.
            $componente->prioridades[$refId] = '';
        }

        $this->gestorVista->sincronizar($componente, $battle);
        $this->session->guardar($componente->battleId, $battle);
    }

    private function aplicar(Combate $componente, AgregadoBatalla $battle, bool $conPrioridades): void
    {
        $equipoJugador = $battle->team1;
        $vivosJugador = $equipoJugador->combatientesCollection()->vivos()->all();

        foreach ($vivosJugador as $combatiente) {
            $postura = PosturaCombate::tryFrom($componente->posturas[$combatiente->id()] ?? '')
                ?? PosturaCombate::ATACAR;
            $combatiente->setPostura($postura);

            if (! $conPrioridades) {
                $combatiente->limpiarPrioridad();

                continue;
            }

            $prioridad = null;
            $indicePrioridad = $componente->prioridades[$combatiente->id()] ?? '';
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
        $this->gestorPrioridades->declararIa($battle);

        $battle->setRondaPreparada($battle->turnManager()->round());
    }

    private function limpiar(Combate $componente): void
    {
        $componente->prioridades = [];
        $componente->prioridadesDisponibles = [];
        $componente->posturas = [];
    }
}
